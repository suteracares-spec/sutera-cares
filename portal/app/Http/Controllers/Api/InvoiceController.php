<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Services\Billing;
use App\Services\InvoiceActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Billing in the app. Every change goes through InvoiceActions, the same
 * code the website uses. The app turns the printable invoice into a PDF
 * on the phone, so no invoice is ever exposed on a public link.
 */
class InvoiceController extends Controller
{
    public function __construct(private Billing $billing, private InvoiceActions $actions) {}

    public function index(Request $request): JsonResponse
    {
        $filter = $request->string('show', 'open')->toString();

        $invoices = Invoice::query()->with(['patient', 'billTo'])
            ->when($filter === 'open', fn ($q) => $q->whereIn('status', ['draft', 'sent', 'part_paid', 'overdue']))
            ->when($filter === 'overdue', fn ($q) => $q->overdue())
            ->when($filter === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($filter === 'void', fn ($q) => $q->where('status', 'void'))
            ->when($request->integer('client'), fn ($q, $id) => $q->where('patient_id', $id))
            ->orderByRaw("status = 'draft' desc")->orderByDesc('id')
            ->limit(100)->get();

        return response()->json([
            'outstanding' => (float) Invoice::outstanding()->sum(DB::raw('total - amount_paid')),
            'overdue'     => (float) Invoice::overdue()->sum(DB::raw('total - amount_paid')),
            'drafts'      => Invoice::where('status', 'draft')->count(),
            'invoices'    => $invoices->map(fn ($i) => $this->summary($i))->values(),
        ]);
    }

    /** Who has work to bill in a month (?month=2026-09, default last month). */
    public function prepare(Request $request): JsonResponse
    {
        [$from, $to] = $this->actions->period($request);

        return response()->json([
            'month'   => $from->format('Y-m'),
            'label'   => $from->format('F Y'),
            'clients' => $this->billing->clientsToBill($from, $to)->map(function ($p) use ($from, $to) {
                $shifts = $this->billing->unbilledShifts($p, $from, $to);

                return [
                    'id'     => $p->id,
                    'name'   => $p->name,
                    'code'   => $p->code,
                    'shifts' => $shifts->count(),
                    'amount' => round($shifts->sum(fn ($s) => $this->billing->lineFor($s)['amount']), 2),
                ];
            })->values(),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        [$made, $message] = $this->actions->generate($request);

        return response()->json(['message' => $message, 'invoice_ids' => $made->pluck('id')->values()]);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        AuditLog::record($request, 'viewed', 'invoice', $invoice->id, "{$invoice->number} (app)");

        return response()->json($this->detail($invoice));
    }

    /** The printable invoice as HTML; the app renders it to a PDF to share. */
    public function html(Invoice $invoice): JsonResponse
    {
        $invoice->load(['patient', 'billTo', 'lines', 'payments']);

        return response()->json([
            'filename' => ($invoice->isDraft() ? 'DRAFT-' . $invoice->id : $invoice->number) . '.pdf',
            'html'     => view('invoices.print', ['invoice' => $invoice])->render(),
        ]);
    }

    public function addLine(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->respond($this->actions->addLine($request, $invoice), $invoice);
    }

    public function removeLine(Invoice $invoice, InvoiceLine $line): JsonResponse
    {
        return $this->respond($this->actions->removeLine($invoice, $line), $invoice);
    }

    public function issue(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->respond($this->actions->issue($request, $invoice), $invoice);
    }

    public function pay(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->respond($this->actions->pay($request, $invoice), $invoice);
    }

    public function void(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->respond($this->actions->void($request, $invoice), $invoice);
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        return response()->json(['message' => $this->actions->destroy($request, $invoice)]);
    }

    // ---- Shapes -----------------------------------------------------------------

    private function respond(string $message, Invoice $invoice): JsonResponse
    {
        return response()->json(['message' => $message] + $this->detail($invoice->fresh()));
    }

    private function summary(Invoice $i): array
    {
        return [
            'id'           => $i->id,
            'number'       => $i->number,
            'client'       => $i->patient?->name,
            'client_id'    => $i->patient_id,
            'bill_to'      => $i->billTo?->name,
            'period'       => $i->period_start?->format('M Y'),
            'status'       => $i->displayStatus(),
            'status_label' => $i->statusLabel(),
            'total'        => (float) $i->total,
            'balance'      => $i->balance(),
            'due_date'     => $i->due_date?->toDateString(),
        ];
    }

    private function detail(Invoice $i): array
    {
        $i->load(['patient', 'billTo', 'lines', 'payments.recordedBy']);

        return $this->summary($i) + [
            'bill_to_email'  => $i->billTo?->email,
            'period_start'   => $i->period_start?->toDateString(),
            'period_end'     => $i->period_end?->toDateString(),
            'issued_at'      => $i->issued_at?->toIso8601String(),
            'subtotal'       => (float) $i->subtotal,
            'adjustments'    => (float) $i->surcharges,
            'amount_paid'    => (float) $i->amount_paid,
            'can_void'       => ! $i->isDraft() && $i->status !== 'void' && $i->payments->isEmpty(),
            'can_pay'        => in_array($i->status, ['sent', 'part_paid', 'overdue'], true),
            'lines'          => $i->lines->map(fn (InvoiceLine $l) => [
                'id'          => $l->id,
                'description' => $l->description,
                'quantity'    => (float) $l->quantity,
                'rate'        => (float) $l->rate,
                'amount'      => (float) $l->amount,
                'from_shift'  => $l->shift_id !== null,
            ])->values(),
            'payments'       => $i->payments->map(fn (Payment $p) => [
                'id'          => $p->id,
                'amount'      => (float) $p->amount,
                'method'      => Payment::METHODS[$p->method] ?? $p->method,
                'reference'   => $p->reference,
                'paid_on'     => $p->paid_on?->toDateString(),
                'recorded_by' => $p->recordedBy?->name,
            ])->values(),
            'methods'        => collect(Payment::METHODS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ];
    }
}
