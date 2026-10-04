<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CarePlanTask;
use App\Models\Concern;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Shift;
use App\Services\FamilyView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Families and clients in the app: the same view as the website's family
 * pages, through FamilyView, with the same per-link rules on visit notes,
 * invoices and care plan change requests.
 */
class FamilyController extends Controller
{
    public function __construct(private FamilyView $family) {}

    // ---- Family -----------------------------------------------------------------

    /** The people this account looks after. */
    public function clients(Request $request): JsonResponse
    {
        $links = $request->user()->guardianLinks()->with('patient')->get()->filter->patient;

        return response()->json(['clients' => $links->map(fn (Guardian $l) => [
            'id'           => $l->patient->id,
            'name'         => $l->patient->name,
            'relationship' => $l->relationship,
        ])->values()]);
    }

    public function client(Request $request, Patient $patient): JsonResponse
    {
        $link = $this->family->link($request, $patient);
        AuditLog::record($request, 'viewed', 'patient', $patient->id, "{$patient->code} by family (app)");

        $plan = $patient->activeCarePlan()?->load('tasks');

        return response()->json([
            'id'                  => $patient->id,
            'name'                => $patient->name,
            'can_view_notes'      => $link->can_view_notes,
            'can_view_invoices'   => $link->can_view_invoices,
            'can_request_changes' => $link->can_request_changes,
            'coming'              => $this->family->coming($patient)->map(fn ($s) => $this->visit($s, false))->values(),
            'visits'              => $this->family->visits($patient)->map(fn ($s) => $this->visit($s, $link->can_view_notes))->values(),
            'plan'                => $plan ? [
                'agreed_at' => $plan->agreed_at?->toDateString(),
                'agreed_by' => $plan->agreed_by,
                'tasks'     => $plan->tasks->map(fn ($t) => [
                    'description' => $t->description,
                    'frequency'   => CarePlanTask::FREQUENCIES[$t->frequency] ?? null,
                ])->values(),
            ] : null,
            'invoices'            => $link->can_view_invoices
                ? Invoice::where('patient_id', $patient->id)->whereNotIn('status', ['draft', 'void'])
                    ->orderByDesc('issued_at')->limit(12)->get()->map(fn (Invoice $i) => [
                        'id'           => $i->id,
                        'number'       => $i->number,
                        'period'       => $i->period_start?->format('M Y'),
                        'total'        => (float) $i->total,
                        'balance'      => $i->balance(),
                        'due_date'     => $i->due_date?->toDateString(),
                        'status'       => $i->displayStatus(),
                        'status_label' => $i->statusLabel(),
                    ])->values()
                : [],
            'concerns'            => $this->concerns($request, $patient),
            'categories'          => $this->categories($link->can_request_changes),
            'bank'                => $link->can_view_invoices ? $this->bank() : null,
        ]);
    }

    public function concern(Request $request, Patient $patient): JsonResponse
    {
        $link = $this->family->link($request, $patient);
        $this->family->raiseConcern($request, $patient, $link->can_request_changes, 'family (app)');

        return response()->json(['message' => 'Thank you. The office has it and your coordinator will be in touch.'], 201);
    }

    /** An invoice as the family receives it, for the app to turn into a PDF. */
    public function invoiceHtml(Request $request, Invoice $invoice): JsonResponse
    {
        $link = $this->family->link($request, $invoice->patient);
        abort_unless($link->can_view_invoices && ! in_array($invoice->status, ['draft', 'void'], true), 404);

        AuditLog::record($request, 'viewed', 'invoice', $invoice->id, "{$invoice->number} by family (app)");

        return response()->json([
            'filename' => "{$invoice->number}.pdf",
            'html'     => view('invoices.print', ['invoice' => $invoice->load(['patient', 'billTo', 'lines', 'payments'])])->render(),
        ]);
    }

    // ---- The client themselves --------------------------------------------------

    public function myCare(Request $request): JsonResponse
    {
        $patient = $request->user()->patientRecord ?? abort(403, 'No client record is linked to this account.');

        return response()->json([
            'name'       => $patient->name,
            'coming'     => $this->family->coming($patient)->map(fn ($s) => $this->visit($s, false))->values(),
            'concerns'   => $this->concerns($request, $patient),
            'categories' => $this->categories(false),
        ]);
    }

    public function myConcern(Request $request): JsonResponse
    {
        $patient = $request->user()->patientRecord ?? abort(403);
        $this->family->raiseConcern($request, $patient, false, 'client (app)');

        return response()->json(['message' => 'Thank you. The office has your message and will be in touch.'], 201);
    }

    // ---- Shapes -----------------------------------------------------------------

    private function visit(Shift $s, bool $withNotes): array
    {
        $log = $s->visitLog;

        return [
            'id'        => $s->id,
            'date'      => $s->shift_date->toDateString(),
            'start'     => substr((string) $s->start_time, 0, 5),
            'end'       => substr((string) $s->end_time, 0, 5),
            'caregiver' => $s->caregiver()?->user?->name,
            'service'   => $s->assignment?->service?->name,
            'status'    => $s->status,
            'arrived'   => $log?->check_in_at?->toIso8601String(),
            'left'      => $log?->check_out_at?->toIso8601String(),
            'done'      => $log?->tasks_completed ?? [],
            'notes'     => $withNotes ? $log?->notes : null,
            'reason'    => $s->status === 'missed' ? $s->cancel_reason : null,
        ];
    }

    private function concerns(Request $request, Patient $patient): array
    {
        return Concern::where('patient_id', $patient->id)->where('raised_by_id', $request->user()->id)
            ->latest()->orderByDesc('id')->limit(10)->get()->map(fn (Concern $c) => [
                'id'       => $c->id,
                'date'     => $c->created_at->toDateString(),
                'category' => str_starts_with((string) $c->detail, 'Care plan change requested')
                    ? 'A change to the care plan'
                    : (Concern::CATEGORIES[$c->category] ?? $c->category),
                'open'     => $c->isOpen(),
            ])->values()->all();
    }

    private function categories(bool $withPlanChange): array
    {
        $kinds = collect(Concern::CATEGORIES)->map(fn ($label, $value) => compact('value', 'label'))->values();

        return ($withPlanChange ? $kinds->prepend(['value' => 'plan_change', 'label' => 'A change to the care plan']) : $kinds)->all();
    }

    /** Where to pay: the same details printed on the invoice. */
    private function bank(): ?array
    {
        return config('billing.account_number') ? [
            'bank'           => config('billing.bank_name'),
            'account_name'   => config('billing.account_name'),
            'account_number' => config('billing.account_number'),
        ] : null;
    }
}
