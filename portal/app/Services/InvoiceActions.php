<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Patient;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Every change to an invoice, shared by the website and the app. Each
 * method validates its own input from the request.
 */
class InvoiceActions
{
    public function __construct(private Billing $billing) {}

    /** The month asked for (?month=2026-09), defaulting to last month: the usual billing run. */
    public function period(Request $request): array
    {
        $month = rescue(fn () => Carbon::createFromFormat('Y-m', (string) $request->input('month')), null, false)
            ?? today()->subMonthNoOverflow();

        return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
    }

    /**
     * Draft invoices for every client with unbilled work in the month, or for one.
     *
     * @return array{0: Collection<int, Invoice>, 1: string}
     */
    public function generate(Request $request): array
    {
        [$from, $to] = $this->period($request);
        $request->validate(['client' => ['nullable', Rule::exists('patients', 'id')]]);

        $clients = $request->filled('client')
            ? collect([Patient::findOrFail($request->integer('client'))])
            : $this->billing->clientsToBill($from, $to);

        $made = $clients->map(fn ($p) => $this->billing->draft($p, $from, $to))->filter()->values();

        AuditLog::record($request, 'invoices_drafted', null, null, "{$made->count()} for {$from->format('M Y')}");

        $message = match ($made->count()) {
            0       => 'Nothing to bill: no completed shifts without an invoice in ' . $from->format('F Y') . '.',
            1       => 'Draft invoice created. Check it, then issue it.',
            default => "{$made->count()} draft invoices created for " . $from->format('F Y') . '. Check each, then issue it.',
        };

        return [$made, $message];
    }

    /** An adjustment on a draft: a holiday surcharge, travel, a discount (negative). */
    public function addLine(Request $request, Invoice $invoice): string
    {
        $this->refuseUnlessDraft($invoice);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:300'],
            'quantity'    => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'rate'        => ['required', 'numeric', 'min:-99999', 'max:99999'],
        ]);

        $invoice->lines()->create($data + ['amount' => round($data['quantity'] * $data['rate'], 2)]);
        $invoice->recalculate();

        return 'Line added.';
    }

    public function removeLine(Invoice $invoice, InvoiceLine $line): string
    {
        $this->refuseUnlessDraft($invoice);
        abort_unless($line->invoice_id === $invoice->id, 404);

        // A removed shift line is simply unbilled again; the next run for
        // that month picks it up.
        $line->delete();
        $invoice->recalculate();

        return 'Line removed.';
    }

    public function issue(Request $request, Invoice $invoice): string
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

        return "Issued as {$invoice->number}, due " . $invoice->due_date->format('j M Y') . '.';
    }

    public function pay(Request $request, Invoice $invoice): string
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

        return $invoice->status === 'paid' ? 'Payment recorded. Paid in full.' : 'Payment recorded.';
    }

    /** Cancel an issued invoice. Kept, numbered, marked void: a gap in the numbering would need explaining. */
    public function void(Request $request, Invoice $invoice): string
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        if ($invoice->isDraft() || $invoice->status === 'void' || $invoice->payments()->exists()) {
            throw ValidationException::withMessages(['reason' => 'Only an issued invoice with no payments can be voided.']);
        }

        $invoice->update(['status' => 'void']);
        AuditLog::record($request, 'voided', 'invoice', $invoice->id, "{$invoice->number}: {$data['reason']}");

        return "{$invoice->number} voided. Its shifts can be billed again.";
    }

    public function destroy(Request $request, Invoice $invoice): string
    {
        $this->refuseUnlessDraft($invoice);

        AuditLog::record($request, 'deleted', 'invoice', $invoice->id, $invoice->number);
        $invoice->delete();

        return 'Draft deleted. Its shifts are unbilled again.';
    }

    private function refuseUnlessDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['issue' => 'This invoice has been issued and can no longer be edited. Void it and bill again if it is wrong.']);
        }
    }
}
