<?php

namespace App\Http\Controllers\Caregiver;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\Concern;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The caregiver's phone, in someone's home. They see their own shifts
 * and nothing else: a client is visible to them only through a shift
 * they are doing. Hiding a link is not access control, so every action
 * checks the shift is theirs.
 */
class VisitController extends Controller
{
    /** How early a caregiver may check in, and how late after the end. */
    private const EARLY_MINUTES = 60;

    private const LATE_MINUTES = 180;

    public function index(Request $request): View
    {
        $me = $this->me($request);

        $base = fn () => Shift::query()->workedBy($me->id)
            ->with(['assignment.patient', 'assignment.service', 'visitLog'])
            ->orderBy('shift_date')->orderBy('start_time');

        return view('caregiver.index', [
            'caregiver' => $me,
            'today'     => $base()->whereDate('shift_date', today())->where('status', '!=', 'cancelled')->get(),
            'coming'    => $base()->whereDate('shift_date', '>', today())
                                  ->whereDate('shift_date', '<=', today()->addDays(7))
                                  ->where('status', 'scheduled')->get(),
            // A visit left open on a past day, so it can be finished.
            'unfinished' => $base()->whereDate('shift_date', '<', today())->where('status', 'in_progress')->get(),
        ]);
    }

    public function show(Request $request, Shift $shift): View
    {
        $me = $this->authorise($request, $shift);
        $shift->load(['assignment.patient', 'assignment.service', 'visitLog']);
        $patient = $shift->assignment->patient;

        AuditLog::record($request, 'viewed', 'patient', $patient->id, "{$patient->code} via shift #{$shift->id}");

        return view('caregiver.shift', [
            'shift'      => $shift,
            'patient'    => $patient,
            'plan'       => $patient->activeCarePlan()?->load('tasks'),
            'canCheckIn' => $this->checkInProblem($shift) === null,
            'whyNot'     => $this->checkInProblem($shift),
            'caregiver'  => $me,
        ]);
    }

    public function checkIn(Request $request, Shift $shift): RedirectResponse
    {
        $me = $this->authorise($request, $shift);

        if ($problem = $this->checkInProblem($shift)) {
            throw ValidationException::withMessages(['check_in' => $problem]);
        }

        // Location is asked for, not demanded: a refused permission or a
        // phone with no fix still checks in, and the record shows which.
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        DB::transaction(function () use ($shift, $me, $data) {
            $shift->visitLog()->create([
                'caregiver_id' => $me->id,
                'check_in_at'  => now(),
                'check_in_lat' => $data['lat'] ?? null,
                'check_in_lng' => $data['lng'] ?? null,
            ]);
            $shift->update(['status' => 'in_progress']);
        });

        AuditLog::record($request, 'checked_in', 'shift', $shift->id,
            isset($data['lat']) ? 'with location' : 'without location');

        return redirect()->route('caregiver.shifts.show', $shift)->with('status', 'Checked in at ' . now()->format('H:i') . '.');
    }

    public function checkOut(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorise($request, $shift);
        $log = $shift->visitLog;

        if ($shift->status !== 'in_progress' || ! $log?->check_in_at) {
            throw ValidationException::withMessages(['notes' => 'Check in before checking out.']);
        }

        $plan = $shift->assignment->patient->activeCarePlan();
        $taskIds = $plan?->tasks()->pluck('id')->all() ?? [];

        $data = $request->validate([
            'tasks'            => ['nullable', 'array'],
            'tasks.*'          => ['integer', Rule::in($taskIds)],
            'notes'            => ['required', 'string', 'max:5000'],
            'concern_flagged'  => ['nullable', 'boolean'],
            'concern_category' => ['required_if:concern_flagged,1', 'nullable', Rule::in(array_keys(Concern::CATEGORIES))],
            'concern_detail'   => ['required_if:concern_flagged,1', 'nullable', 'string', 'max:600'],
        ], [
            'notes.required'             => 'Write a short visit note: how they were, anything the family or the next caregiver should know.',
            'concern_detail.required_if' => 'Describe the concern so the office can act on it.',
        ]);

        // A snapshot of the wording, not just ids: the plan may be revised
        // later, and the record must say what was done on the day.
        $done = $plan ? $plan->tasks()->whereIn('id', $data['tasks'] ?? [])->pluck('description')->all() : [];
        $flagged = (bool) ($data['concern_flagged'] ?? false);

        DB::transaction(function () use ($shift, $log, $data, $done, $flagged, $request) {
            $log->update([
                'check_out_at'    => now(),
                'minutes_worked'  => (int) $log->check_in_at->diffInMinutes(now()),
                'tasks_completed' => $done,
                'notes'           => $data['notes'],
                'concern_flagged' => $flagged,
                'concern_detail'  => $flagged ? $data['concern_detail'] : null,
            ]);
            $shift->update(['status' => 'completed']);

            if ($flagged) {
                Concern::create([
                    'raised_by_id' => $request->user()->id,
                    'patient_id'   => $shift->assignment->patient_id,
                    'shift_id'     => $shift->id,
                    'category'     => $data['concern_category'],
                    'detail'       => $data['concern_detail'],
                    'status'       => 'open',
                ]);
            }
        });

        AuditLog::record($request, 'checked_out', 'shift', $shift->id, $flagged ? 'concern flagged' : null);

        return redirect()->route('caregiver.dashboard')->with('status', 'Visit recorded. Thank you.'
            . ($flagged ? ' The office has been told about your concern.' : ''));
    }

    /** Why this shift cannot be checked into now, or null if it can. */
    private function checkInProblem(Shift $shift): ?string
    {
        return match (true) {
            $shift->status === 'cancelled'    => 'This shift was cancelled.',
            $shift->status !== 'scheduled'    => null === $shift->visitLog ? 'This shift is ' . $shift->status . '.' : 'Already checked in.',
            now()->lt($shift->startsAt()->subMinutes(self::EARLY_MINUTES))
                => 'Check-in opens an hour before the shift, at ' . $shift->startsAt()->subMinutes(self::EARLY_MINUTES)->format('H:i') . '.',
            now()->gt($shift->endsAt()->addMinutes(self::LATE_MINUTES))
                => 'This shift ended too long ago to check in. Tell the office what happened.',
            default => null,
        };
    }

    private function me(Request $request): Caregiver
    {
        // A caregiver login with no staff record, or an archived one, has
        // no shifts to see.
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
