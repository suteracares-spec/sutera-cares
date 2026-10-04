<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\Concern;
use App\Models\Enquiry;
use App\Models\Patient;
use App\Models\Shift;
use App\Models\User;
use App\Services\OfficeActions;
use App\Services\ShiftActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The office's day-to-day in the app: the week's schedule, what can be
 * done to a shift, concern handling and the enquiries inbox. Every change
 * goes through the same ShiftActions / OfficeActions as the website, so
 * the rules, messages and audit trail are identical. Each change answers
 * with a sentence for the person and the record as it now stands.
 */
class OfficeActionsController extends Controller
{
    public function __construct(
        private ShiftActions $shifts,
        private OfficeActions $office,
        private OfficeController $views,
    ) {}

    // ---- Schedule -------------------------------------------------------

    /** Seven days from Monday of the given week, filterable by caregiver or client. */
    public function week(Request $request): JsonResponse
    {
        $monday = rescue(fn () => Carbon::parse($request->query('week', 'today')), today(), false)->startOfWeek();
        $caregiverId = $request->integer('caregiver') ?: null;
        $patientId = $request->integer('client') ?: null;

        $shifts = Shift::query()
            ->with(['assignment.patient', 'assignment.caregiver.user', 'assignment.service', 'coveredBy.user', 'visitLog'])
            ->whereDate('shift_date', '>=', $monday->toDateString())
            ->whereDate('shift_date', '<=', $monday->copy()->addDays(6)->toDateString())
            ->when($caregiverId, fn ($q) => $q->workedBy($caregiverId))
            ->when($patientId, fn ($q) => $q->whereHas('assignment', fn ($a) => $a->where('patient_id', $patientId)))
            ->orderBy('shift_date')->orderBy('start_time')
            ->get();

        return response()->json([
            'week'  => $monday->toDateString(),
            'days'  => collect(range(0, 6))->map(fn ($i) => $monday->copy()->addDays($i)->toDateString()),
            'shifts' => $shifts->map(fn ($s) => $this->views->shiftSummary($s))->values(),
            'caregivers' => Caregiver::with('user')->whereIn('status', ['active', 'inactive'])->get()
                ->sortBy('user.name')->map(fn ($c) => ['id' => $c->id, 'name' => $c->user?->name])->values(),
            'clients' => Patient::whereIn('status', ['active', 'assessment', 'paused'])->orderBy('name')
                ->get(['id', 'name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]),
        ]);
    }

    // ---- Shift actions --------------------------------------------------

    public function move(Request $request, Shift $shift): JsonResponse
    {
        return $this->shiftDone($request, $shift, $this->shifts->move($request, $shift));
    }

    public function cancel(Request $request, Shift $shift): JsonResponse
    {
        return $this->shiftDone($request, $shift, $this->shifts->cancel($request, $shift));
    }

    public function cover(Request $request, Shift $shift): JsonResponse
    {
        return $this->shiftDone($request, $shift, $this->shifts->cover($request, $shift));
    }

    public function missed(Request $request, Shift $shift): JsonResponse
    {
        return $this->shiftDone($request, $shift, $this->shifts->missed($request, $shift));
    }

    public function correct(Request $request, Shift $shift): JsonResponse
    {
        return $this->shiftDone($request, $shift, $this->shifts->correct($request, $shift));
    }

    /** Who could be sent instead: placeable caregivers other than the assigned one. */
    public function relief(Shift $shift): JsonResponse
    {
        return response()->json([
            'caregivers' => $this->shifts->reliefOptions($shift)
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->user?->name, 'code' => $c->code, 'area' => $c->base_area])
                ->values(),
        ]);
    }

    // ---- Concerns -------------------------------------------------------

    public function updateConcern(Request $request, Concern $concern): JsonResponse
    {
        $message = $this->office->updateConcern($request, $concern);

        return response()->json(['message' => $message] + $this->views->concern($request, $concern->fresh())->getData(true));
    }

    /** Office staff who can own a concern. */
    public function staff(): JsonResponse
    {
        return response()->json([
            'staff' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_COORDINATOR])->where('status', 'active')
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ---- Enquiries ------------------------------------------------------

    public function enquiries(Request $request): JsonResponse
    {
        $show = $request->query('show', 'open');

        $enquiries = Enquiry::query()
            ->when($show === 'open', fn ($q) => $q->whereIn('status', ['new', 'contacted', 'assessment_booked']))
            ->when($show === 'closed', fn ($q) => $q->whereIn('status', ['converted', 'declined', 'lost']))
            ->orderByRaw("status = 'new' desc")->latest()->limit(100)->get();

        return response()->json([
            'new_count' => Enquiry::where('status', 'new')->count(),
            'enquiries' => $enquiries->map(fn ($e) => $this->enquirySummary($e))->values(),
        ]);
    }

    public function enquiry(Request $request, Enquiry $enquiry): JsonResponse
    {
        AuditLog::record($request, 'viewed', 'enquiry', $enquiry->id, 'app');

        return response()->json($this->enquiryDetail($enquiry));
    }

    public function enquiryStatus(Request $request, Enquiry $enquiry): JsonResponse
    {
        $message = $this->office->enquiryStatus($request, $enquiry);

        return response()->json(['message' => $message] + $this->enquiryDetail($enquiry->fresh()));
    }

    public function convertEnquiry(Request $request, Enquiry $enquiry): JsonResponse
    {
        $already = (bool) $enquiry->patient_id;
        $patient = $this->office->convertEnquiry($request, $enquiry);

        return response()->json([
            'message'    => $already ? 'This enquiry was already converted.'
                : "Client {$patient->code} created. Record consent before care begins.",
            'patient_id' => $patient->id,
        ] + $this->enquiryDetail($enquiry->fresh()));
    }

    // ---- Shapes ---------------------------------------------------------

    private function shiftDone(Request $request, Shift $shift, string $message): JsonResponse
    {
        return response()->json(['message' => $message] + $this->views->shift($request, $shift->fresh())->getData(true));
    }

    private function enquirySummary(Enquiry $e): array
    {
        return [
            'id'          => $e->id,
            'status'      => $e->status,
            'client_name' => $e->client_name,
            'patient_name' => $e->patient_name,
            'area'        => $e->patient_area,
            'received_at' => $e->created_at->toIso8601String(),
            'converted'   => $e->patient_id !== null,
        ];
    }

    private function enquiryDetail(Enquiry $e): array
    {
        return $this->enquirySummary($e) + [
            'client_phone'        => $e->client_phone,
            'client_email'        => $e->client_email,
            'client_relationship' => $e->client_relationship,
            'patient_age'         => $e->patient_age,
            'patient_mobility'    => $e->patient_mobility,
            'needs'               => $e->needs,
            'schedule_wanted'     => $e->schedule_wanted,
            'patient_id'          => $e->patient_id,
            'statuses'            => OfficeActions::ENQUIRY_STATUSES,
        ];
    }
}
