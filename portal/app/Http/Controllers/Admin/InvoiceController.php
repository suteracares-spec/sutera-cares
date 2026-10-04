<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Patient;
use App\Models\Payment;
use App\Services\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Invoice and bank transfer: the office generates invoices from completed
 * shifts, sends them, and records payments by hand when they arrive. No
 * payment gateway.
 */
class InvoiceController extends Controller
{
    public function __construct(private Billing $billing) {}

    public function index(Request $request): View
    {
        $filter = $request->query('show', 'open');

        $invoices = Invoice::query()->with(['patient', 'billTo'])
            ->when($filter === 'open', fn ($q) => $q->whereIn('status', ['draft', 'sent', 'part_paid', 'overdue']))
            ->when($filter === 'overdue', fn ($q) => $q->overdue())
            ->when($filter === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($filter === 'void', fn ($q) => $q->where('status', 'void'))
            ->when($request->integer('client'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByRaw("status = 'draft' desc")->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('admin.invoices.index', [
            'invoices'    => $invoices,
            'filter'      => $filter,
            'outstanding' => (float) Invoice::outstanding()->sum(DB::raw('total - amount_paid')),
            'overdue'     => (float) Invoice::overdue()->sum(DB::raw('total - amount_paid')),
            'drafts'      => Invoice::where('status', 'draft')->count(),
        ]);
    }

    /** Pick a month; see who has work to bill in it. */
    public function prepare(Request $request): View
    {
        [$from, $to] = $this->period($request);

        return view('admin.invoices.prepare', [
            'from'    => $from,
            'to'      => $to,
            'clients' => $this->billing->clientsToBill($from, $to)
                ->map(fn ($p) => [$p, $this->billing->unbilledShifts($p, $from, $to)]),
        ]);
    }

    /** Draft invoices for every client with unbilled work in the month, or for one. */
    public function generate(Request $request): RedirectResponse
    {
        [$from, $to] = $this->period($request);
        $request->validate(['client' => ['nullable', Rule::exists('patients', 'id')]]);

        $clients = $request->filled('client')
            ? collect([Patient::findOrFail($request->integer('client'))])
            : $this->billing->clientsToBill($from, $to);

        $made = $clients->map(fn ($p) => $this->billing->draft($p, $from, $to))->filter();

        AuditLog::record($request, 'invoices_drafted', null, null, "{$made->count()} for {$from->format('M Y')}");

        if ($made->count() === 1) {
            return redirect()->route('admin.invoices.show', $made->first())->with('status', 'Draft invoice created. Check it, then issue it.');
        }

        return redirect()->route('admin.invoices.index')->with('status', $made->isEmpty()
            ? 'Nothing to bill: no completed shifts without an invoice in ' . $from->format('F Y') . '.'
            : "{$made->count()} draft invoices created for " . $from->format('F Y') . '. Check each, then issue it.');
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $invoice->load(['patient', 'billTo', 'lines.shift', 'payments.recordedBy']);
        AuditLog::record($request, 'viewed', 'invoice', $invoice->id, $invoice->number);

        return view('admin.invoices.show', ['invoice' => $invoice]);
    }

    /** The invoice as the family receives it: print it, or save it as a PDF from the browser. */
    public function print(Request $request, Invoice $invoice): View
    {
        $invoice->load(['patient', 'billTo', 'lines', 'payments']);

        return view('invoices.print', ['invoice' => $invoice]);
    }

    /** An adjustment on a draft: a holiday surcharge, travel, a discount (negative). */
    public function addLine(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->refuseUnlessDraft($invoice);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:300'],
            'quantity'    => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'rate'        => ['required', 'numeric', 'min:-99999', 'max:99999'],
        ]);

        $invoice->lines()->create($data + ['amount' => round($data['quantity'] * $data['rate'], 2)]);
        $invoice->recalculate();

        return back()->with('status', 'Line added.');
    }

    public function removeLine(Request $request, Invoice $invoice, InvoiceLine $line): RedirectResponse
    {
        $this->refuseUnlessDraft($invoice);
        abort_unless($line->invoice_id === $invoice->id, 404);

        // A removed shift line is simply unbilled again; the next run for
        // that month picks it up.
        $line->delete();
        $invoice->recalculate();

        return back()->with('status', 'Line removed.');
    }

    public function issue(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->refuseUnlessDraft($invoice);

        if ($invoice->lines()->doesntExist() || (float) $invoice->total <= 0) {
            throw ValidationException::withMessages(['issue' => 'An invoice needs at least one line and a total above zero.']);
        }

        DB::transaction(function () use ($invoice) {
            $invoice->update([
                'number'    => Invoice::nextNumber(),
                'status'    => 'sent',
                'issued_at' => now(),
                'due_date'  => today()->addDays(config('billing.due_days')),
            ]);
        });

        AuditLog::record($request, 'issued', 'invoice', $invoice->id, "{$invoice->number}, RM {$invoice->total}");

        return back()->with('status', "Issued as {$invoice->number}, due " . $invoice->due_date->format('j M Y')
            . '. Print it or save it as a PDF to send.');
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        if (! in_array($invoice->status, ['sent', 'part_paid', 'overdue'], true)) {
            throw ValidationException::withMessages(['amount' => 'Payments are recorded against issued, unpaid invoices.']);
        }

        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01', 'max:' . $invoice->balance()],
            'method'    => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_on'   => ['required', 'date', 'before_or_equal:today'],
        ], ['amount.max' => 'That is more than the RM ' . number_format($invoice->balance(), 2) . ' still owed.']);

        DB::transaction(function () use ($invoice, $data, $request) {
            $invoice->payments()->create($data + ['recorded_by_id' => $request->user()->id]);
            $paid = round((float) $invoice->amount_paid + (float) $data['amount'], 2);
            $invoice->update([
                'amount_paid' => $paid,
                'status'      => $paid >= (float) $invoice->total ? 'paid' : 'part_paid',
            ]);
        });

        AuditLog::record($request, 'payment_recorded', 'invoice', $invoice->id, "RM {$data['amount']} {$data['method']}");

        return back()->with('status', $invoice->status === 'paid' ? 'Payment recorded. Paid in full.' : 'Payment recorded.');
    }

    /** Cancel an issued invoice. Kept, numbered, marked void: a gap in the numbering would need explaining. */
    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        if ($invoice->isDraft() || $invoice->status === 'void' || $invoice->payments()->exists()) {
            throw ValidationException::withMessages(['reason' => 'Only an issued invoice with no payments can be voided.']);
        }

        $invoice->update(['status' => 'void']);
        AuditLog::record($request, 'voided', 'invoice', $invoice->id, "{$invoice->number}: {$data['reason']}");

        return back()->with('status', "{$invoice->number} voided. Its shifts can be billed again.");
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->refuseUnlessDraft($invoice);

        AuditLog::record($request, 'deleted', 'invoice', $invoice->id, $invoice->number);
        $invoice->delete();

        return redirect()->route('admin.invoices.index')->with('status', 'Draft deleted. Its shifts are unbilled again.');
    }

    /** The month asked for, defaulting to last month: the usual billing run. */
    private function period(Request $request): array
    {
        $month = rescue(fn () => Carbon::createFromFormat('Y-m', (string) $request->input('month')), null, false)
            ?? today()->subMonthNoOverflow();

        return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
    }

    private function refuseUnlessDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['issue' => 'This invoice has been issued and can no longer be edited. Void it and bill again if it is wrong.']);
        }
    }
}
