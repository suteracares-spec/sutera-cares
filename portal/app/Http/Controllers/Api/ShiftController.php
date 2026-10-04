<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\CarePlanTask;
use App\Models\Concern;
use App\Models\Shift;
use App\Services\Visits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The caregiver app's shifts and visits. Same rules as the web view (they
 * share Visits), plus what an app needs: the time the caregiver tapped is
 * sent with the request, because a check-in made in a lift with no signal
 * arrives later; and every action is safe to send twice, because a phone
 * that lost its connection mid-request will retry.
 */
class ShiftController extends Controller
{
    public function __construct(private Visits $visits) {}

    public function index(Request $request): JsonResponse
    {
        $lists = $this->visits->shiftsFor($this->me($request));

        return response()->json(array_map(fn ($list) => $list->map(fn ($s) => $this->summary($s))->values(), $lists)
            + ['server_time' => now()->toIso8601String()]);
    }

    public function show(Request $request, Shift $shift): JsonResponse
    {
        $this->authorise($request, $shift);
        $patient = $shift->assignment->patient;
        AuditLog::record($request, 'viewed', 'patient', $patient->id, "{$patient->code} via shift #{$shift->id} (app)");

        return response()->json($this->detail($shift));
    }

    public function checkIn(Request $request, Shift $shift): JsonResponse
    {
        $me = $this->authorise($request, $shift);

        $data = $request->validate([
            'at'  => ['nullable', 'date'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Sent twice (a retry after a dropped connection)? Already done: say so.
        if ($shift->visitLog) {
            return response()->json($this->detail($shift) + ['already' => true]);
        }

        $at = $this->tappedAt($data['at'] ?? null);
        if ($problem = $this->visits->checkInProblem($shift, $at)) {
            throw ValidationException::withMessages(['check_in' => $problem]);
        }

        $this->visits->checkIn($shift, $me, $at, $data['lat'] ?? null, $data['lng'] ?? null);

        AuditLog::record($request, 'checked_in', 'shift', $shift->id, 'app, '
            . (isset($data['lat']) ? 'with location' : 'without location')
            . ($at->lt(now()->subMinutes(2)) ? ", tapped {$at->format('H:i')}, received " . now()->format('H:i') : ''));

        return response()->json($this->detail($shift->fresh()));
    }

    public function checkOut(Request $request, Shift $shift): JsonResponse
    {
        $this->authorise($request, $shift);

        if ($shift->status === 'completed') {
            return response()->json($this->detail($shift) + ['already' => true]);
        }

        if ($shift->status !== 'in_progress' || ! $shift->visitLog?->check_in_at) {
            throw ValidationException::withMessages(['notes' => 'Check in before checking out.']);
        }

        $data = $request->validate([
            'at'               => ['nullable', 'date'],
            'tasks'            => ['nullable', 'array'],
            'tasks.*'          => ['integer', Rule::in($this->visits->taskIds($shift))],
            'notes'            => ['required', 'string', 'max:5000'],
            'concern_flagged'  => ['nullable', 'boolean'],
            'concern_category' => ['required_if:concern_flagged,true', 'nullable', Rule::in(array_keys(Concern::CATEGORIES))],
            'concern_detail'   => ['required_if:concern_flagged,true', 'nullable', 'string', 'max:600'],
        ], [
            'notes.required'             => 'Write a short visit note.',
            'concern_detail.required_if' => 'Describe the concern so the office can act on it.',
        ]);

        $at = $this->tappedAt($data['at'] ?? null);
        if ($at->lt($shift->visitLog->check_in_at)) {
            throw ValidationException::withMessages(['at' => 'Check-out cannot be before check-in.']);
        }

        $flagged = (bool) ($data['concern_flagged'] ?? false);
        $this->visits->checkOut($shift, $request->user(), $at, $data['tasks'] ?? [], $data['notes'],
            $flagged, $data['concern_category'] ?? null, $data['concern_detail'] ?? null);

        AuditLog::record($request, 'checked_out', 'shift', $shift->id, 'app' . ($flagged ? ', concern flagged' : ''));

        return response()->json($this->detail($shift->fresh()));
    }

    /** The phone's time for the action, checked; now if none was sent. */
    private function tappedAt(?string $value): Carbon
    {
        $at = $value ? Carbon::parse($value)->setTimezone(config('app.timezone')) : now();

        if ($problem = $this->visits->timeProblem($at)) {
            throw ValidationException::withMessages(['at' => $problem]);
        }

        return $at;
    }

    private function summary(Shift $shift): array
    {
        $patient = $shift->assignment->patient;

        return [
            'id'      => $shift->id,
            'date'    => $shift->shift_date->toDateString(),
            'start'   => substr($shift->start_time, 0, 5),
            'end'     => substr($shift->end_time, 0, 5),
            'status'  => $shift->status,
            'client'  => ['name' => $patient->name, 'area' => $patient->area],
            'service' => $shift->assignment->service?->name,
        ];
    }

    /** Everything the visit screen needs, and only that: no IC number, no office notes. */
    private function detail(Shift $shift): array
    {
        $shift->loadMissing(['assignment.patient', 'assignment.service', 'visitLog']);
        $patient = $shift->assignment->patient;
        $plan = $patient->activeCarePlan()?->load('tasks');
        $log = $shift->visitLog;

        return $this->summary($shift) + [
            'cancel_reason'    => $shift->status === 'cancelled' ? $shift->cancel_reason : null,
            'check_in_problem' => $shift->status === 'scheduled' ? $this->visits->checkInProblem($shift) : null,
            'opens_at'         => $shift->startsAt()->subMinutes(Visits::EARLY_MINUTES)->toIso8601String(),
            'client_details'   => [
                'address'   => $patient->address,
                'postcode'  => $patient->postcode,
                'mobility'  => $patient->mobility_level ? ucfirst(str_replace('_', ' ', $patient->mobility_level)) : null,
                'languages' => $patient->languages,
                'allergies' => $patient->allergies,
            ],
            'plan' => $plan ? [
                'notes' => $plan->notes,
                'tasks' => $plan->tasks->map(fn ($t) => [
                    'id'          => $t->id,
                    'description' => $t->description,
                    'category'    => CarePlanTask::CATEGORIES[$t->category] ?? $t->category,
                    'when'        => trim((CarePlanTask::FREQUENCIES[$t->frequency] ?? '')
                                     . ($t->time_of_day !== 'any' ? ', ' . strtolower(CarePlanTask::TIMES[$t->time_of_day] ?? '') : '')),
                ])->values(),
            ] : null,
            'visit' => $log ? [
                'check_in_at'     => $log->check_in_at?->toIso8601String(),
                'check_out_at'    => $log->check_out_at?->toIso8601String(),
                'tasks_completed' => $log->tasks_completed ?? [],
                'notes'           => $log->notes,
                'concern_flagged' => $log->concern_flagged,
            ] : null,
        ];
    }

    private function me(Request $request): Caregiver
    {
        return $request->user()->caregiver ?? abort(403, 'No caregiver record is linked to this account.');
    }

    /** The shift must be one this caregiver is doing. 404, not 403: do not confirm it exists. */
    private function authorise(Request $request, Shift $shift): Caregiver
    {
        $me = $this->me($request);
        abort_unless($shift->caregiverId() === $me->id, 404);

        return $me;
    }
}
