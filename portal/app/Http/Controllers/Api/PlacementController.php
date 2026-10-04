<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\Patient;
use App\Models\Service;
use App\Services\PlacementActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Caregivers and placements in the app. Every change goes through
 * PlacementActions, the same code the website uses.
 */
class PlacementController extends Controller
{
    public function __construct(private PlacementActions $actions, private OfficeController $views) {}

    // ---- Caregivers -----------------------------------------------------------

    public function caregivers(Request $request): JsonResponse
    {
        $caregivers = Caregiver::query()->with('user')
            ->when($request->string('q')->toString(), fn ($query, $term) => $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$term}%")
                ->orWhere('base_area', 'like', "%{$term}%")
                ->orWhere('skills', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"))))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->get()->sortBy(fn ($c) => [$c->status === 'active' ? 0 : 1, $c->user?->name])->values();

        return response()->json(['caregivers' => $caregivers->map(fn ($c) => $this->caregiverSummary($c))]);
    }

    public function caregiver(Request $request, Caregiver $caregiver): JsonResponse
    {
        AuditLog::record($request, 'viewed', 'caregiver', $caregiver->id, "{$caregiver->code} (app)");

        return response()->json($this->caregiverDetail($caregiver));
    }

    public function storeCaregiver(Request $request): JsonResponse
    {
        $caregiver = $this->actions->createCaregiver($request);

        return response()->json(['message' => "Caregiver {$caregiver->code} created. Set up their sign-in to let them use the app."]
            + $this->caregiverDetail($caregiver), 201);
    }

    public function updateCaregiver(Request $request, Caregiver $caregiver): JsonResponse
    {
        $message = $this->actions->updateCaregiver($request, $caregiver);

        return response()->json(['message' => $message] + $this->caregiverDetail($caregiver->fresh()));
    }

    public function archiveCaregiver(Request $request, Caregiver $caregiver): JsonResponse
    {
        return response()->json(['message' => $this->actions->archiveCaregiver($request, $caregiver)]);
    }

    // ---- Assignments ------------------------------------------------------------

    /** What the assign form offers: placeable caregivers, services, and whether care can be placed yet. */
    public function options(Request $request): JsonResponse
    {
        $patient = $request->integer('client') ? Patient::find($request->integer('client')) : null;

        return response()->json([
            'caregivers' => $this->actions->placeable()->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->user?->name, 'code' => $c->code,
                'area' => $c->base_area, 'gender' => $c->gender, 'languages' => $c->languages,
            ])->values(),
            'services' => Service::where('active', true)->orderBy('name')->get()->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'category' => $s->category, 'unit' => $s->unit,
                'base_rate' => (float) $s->base_rate,
                'needs_care_plan' => in_array($s->category, Assignment::NEEDS_CARE_PLAN, true),
            ])->values(),
            'client_has_care_plan' => (bool) $patient?->activeCarePlan(),
        ]);
    }

    public function storeAssignment(Request $request, Patient $patient): JsonResponse
    {
        [$assignment, $message] = $this->actions->createAssignment($request, $patient);

        return response()->json(['message' => $message] + $this->assignmentDetail($assignment), 201);
    }

    public function assignment(Request $request, Assignment $assignment): JsonResponse
    {
        return response()->json($this->assignmentDetail($assignment));
    }

    public function updateAssignment(Request $request, Assignment $assignment): JsonResponse
    {
        $message = $this->actions->updateAssignment($request, $assignment);

        return response()->json(['message' => $message] + $this->assignmentDetail($assignment->fresh()));
    }

    public function endAssignment(Request $request, Assignment $assignment): JsonResponse
    {
        $message = $this->actions->endAssignment($request, $assignment);

        return response()->json(['message' => $message] + $this->assignmentDetail($assignment->fresh()));
    }

    public function generate(Request $request, Assignment $assignment): JsonResponse
    {
        $message = $this->actions->generateShifts($request, $assignment);

        return response()->json(['message' => $message] + $this->assignmentDetail($assignment->fresh()));
    }

    public function addShift(Request $request, Assignment $assignment): JsonResponse
    {
        $shift = $this->actions->addShift($request, $assignment);

        return response()->json(['message' => 'Shift booked for ' . $shift->shift_date->format('D j M') . '.']
            + $this->assignmentDetail($assignment->fresh()));
    }

    // ---- Shapes -----------------------------------------------------------------

    private function caregiverSummary(Caregiver $c): array
    {
        return [
            'id'             => $c->id,
            'code'           => $c->code,
            'name'           => $c->user?->name,
            'area'           => $c->base_area,
            'status'         => $c->status,
            'placeable'      => $c->isPlaceable(),
            'check_expiring' => $c->status === 'active' && $c->policeCheckExpiringSoon(),
        ];
    }

    private function caregiverDetail(Caregiver $c): array
    {
        $c->load(['user', 'assignments' => fn ($q) => $q->with(['patient', 'service'])->orderByDesc('start_date')]);

        return $this->caregiverSummary($c) + [
            'email'                   => $c->user?->email,
            'phone'                   => $c->user?->phone,
            'user_id'                 => $c->user_id,
            'login_status'            => $c->user?->status,
            'last_login_at'           => $c->user?->last_login_at?->toIso8601String(),
            'ic_number'               => $c->ic_number,
            'gender'                  => $c->gender,
            'dob'                     => $c->dob?->toDateString(),
            'languages'               => $c->languages,
            'skills'                  => $c->skills,
            'base_area'               => $c->base_area,
            'has_own_transport'       => $c->has_own_transport,
            'max_travel_km'           => $c->max_travel_km,
            'hourly_rate'             => $c->hourly_rate !== null ? (float) $c->hourly_rate : null,
            'police_check_expires_at' => $c->police_check_expires_at?->toDateString(),
            'right_to_work_verified'  => $c->right_to_work_verified,
            'assignments'             => $c->assignments->map(fn (Assignment $a) => [
                'id'         => $a->id,
                'client'     => $a->patient?->name,
                'service'    => $a->service?->name,
                'role'       => $a->role,
                'status'     => $a->status,
                'start_date' => $a->start_date?->toDateString(),
                'end_date'   => $a->end_date?->toDateString(),
            ])->values(),
        ];
    }

    private function assignmentDetail(Assignment $a): array
    {
        $a->load(['patient', 'caregiver.user', 'service']);

        $shifts = fn () => $a->shifts()->with(['assignment.patient', 'assignment.caregiver.user', 'assignment.service', 'coveredBy.user', 'visitLog']);

        return [
            'id'          => $a->id,
            'client'      => ['id' => $a->patient_id, 'name' => $a->patient?->name, 'code' => $a->patient?->code],
            'caregiver'   => ['id' => $a->caregiver_id, 'name' => $a->caregiver?->user?->name, 'code' => $a->caregiver?->code],
            'service'     => $a->service ? ['name' => $a->service->name, 'unit' => $a->service->unit] : null,
            'role'        => $a->role,
            'status'      => $a->status,
            'one_off'     => $a->isOneOff(),
            'start_date'  => $a->start_date?->toDateString(),
            'end_date'    => $a->end_date?->toDateString(),
            'charge_rate' => $a->rate() !== null ? (float) $a->rate() : null,
            'notes'       => $a->notes,
            'upcoming'    => $shifts()->whereDate('shift_date', '>=', today())->orderBy('shift_date')->orderBy('start_time')
                                ->limit(60)->get()->map(fn ($s) => $this->views->shiftSummary($s))->values(),
            'recent'      => $shifts()->whereDate('shift_date', '<', today())->orderByDesc('shift_date')->orderByDesc('start_time')
                                ->limit(20)->get()->map(fn ($s) => $this->views->shiftSummary($s))->values(),
        ];
    }
}
