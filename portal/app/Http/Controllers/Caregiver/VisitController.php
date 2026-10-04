<?php

namespace App\Http\Controllers\Caregiver;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\Concern;
use App\Models\Shift;
use App\Services\Visits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The caregiver's phone, in someone's home. They see their own shifts
 * and nothing else: a client is visible to them only through a shift
 * they are doing. Hiding a link is not access control, so every action
 * checks the shift is theirs. The rules themselves live in Visits, shared
 * with the Android app.
 */
class VisitController extends Controller
{
    public function __construct(private Visits $visits) {}

    public function index(Request $request): View
    {
        $me = $this->me($request);

        return view('caregiver.index', ['caregiver' => $me] + $this->visits->shiftsFor($me));
    }

    public function show(Request $request, Shift $shift): View
    {
        $me = $this->authorise($request, $shift);
        $shift->load(['assignment.patient', 'assignment.service', 'visitLog']);
        $patient = $shift->assignment->patient;

        AuditLog::record($request, 'viewed', 'patient', $patient->id, "{$patient->code} via shift #{$shift->id}");

        $whyNot = $this->visits->checkInProblem($shift);

        return view('caregiver.shift', [
            'shift'      => $shift,
            'patient'    => $patient,
            'plan'       => $patient->activeCarePlan()?->load('tasks'),
            'canCheckIn' => $whyNot === null,
            'whyNot'     => $whyNot,
            'caregiver'  => $me,
        ]);
    }

    public function checkIn(Request $request, Shift $shift): RedirectResponse
    {
        $me = $this->authorise($request, $shift);

        if ($problem = $this->visits->checkInProblem($shift)) {
            throw ValidationException::withMessages(['check_in' => $problem]);
        }

        // Location is asked for, not demanded: a refused permission or a
        // phone with no fix still checks in, and the record shows which.
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->visits->checkIn($shift, $me, now(), $data['lat'] ?? null, $data['lng'] ?? null);

        AuditLog::record($request, 'checked_in', 'shift', $shift->id,
            isset($data['lat']) ? 'with location' : 'without location');

        return redirect()->route('caregiver.shifts.show', $shift)->with('status', 'Checked in at ' . now()->format('H:i') . '.');
    }

    public function checkOut(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorise($request, $shift);

        if ($shift->status !== 'in_progress' || ! $shift->visitLog?->check_in_at) {
            throw ValidationException::withMessages(['notes' => 'Check in before checking out.']);
        }

        $data = $request->validate([
            'tasks'            => ['nullable', 'array'],
            'tasks.*'          => ['integer', Rule::in($this->visits->taskIds($shift))],
            'notes'            => ['required', 'string', 'max:5000'],
            'concern_flagged'  => ['nullable', 'boolean'],
            'concern_category' => ['required_if:concern_flagged,1', 'nullable', Rule::in(array_keys(Concern::CATEGORIES))],
            'concern_detail'   => ['required_if:concern_flagged,1', 'nullable', 'string', 'max:600'],
        ], [
            'notes.required'             => 'Write a short visit note: how they were, anything the family or the next caregiver should know.',
            'concern_detail.required_if' => 'Describe the concern so the office can act on it.',
        ]);

        $flagged = (bool) ($data['concern_flagged'] ?? false);
        $this->visits->checkOut($shift, $request->user(), now(), $data['tasks'] ?? [], $data['notes'],
            $flagged, $data['concern_category'] ?? null, $data['concern_detail'] ?? null);

        AuditLog::record($request, 'checked_out', 'shift', $shift->id, $flagged ? 'concern flagged' : null);

        return redirect()->route('caregiver.dashboard')->with('status', 'Visit recorded. Thank you.'
            . ($flagged ? ' The office has been told about your concern.' : ''));
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
