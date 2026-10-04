<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\Billing;
use App\Services\InvoiceActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Invoice and bank transfer: the office generates invoices from completed
 * shifts, sends them, and records payments by hand when they arrive. No
 * payment gateway.
 */
class InvoiceController extends Controller
{
    public function __construct(private Billing $billing, private InvoiceActions $actions) {}

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
        [$from, $to] = $this->actions->period($request);

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
        [$made, $message] = $this->actions->generate($request);

        return $made->count() === 1
            ? redirect()->route('admin.invoices.show', $made->first())->with('status', $message)
            : redirect()->route('admin.invoices.index')->with('status', $message);
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
        return back()->with('status', $this->actions->addLine($request, $invoice));
    }

    public function removeLine(Request $request, Invoice $invoice, InvoiceLine $line): RedirectResponse
    {
        return back()->with('status', $this->actions->removeLine($invoice, $line));
    }

    public function issue(Request $request, Invoice $invoice): RedirectResponse
    {
        return back()->with('status', $this->actions->issue($request, $invoice) . ' Print it or save it as a PDF to send.');
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        return back()->with('status', $this->actions->pay($request, $invoice));
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        return back()->with('status', $this->actions->void($request, $invoice));
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        return redirect()->route('admin.invoices.index')->with('status', $this->actions->destroy($request, $invoice));
    }
}
