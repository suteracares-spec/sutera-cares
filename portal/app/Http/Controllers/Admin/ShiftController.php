<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\Shift;
use App\Services\Scheduler;
use App\Services\ShiftActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The office side of shifts: book them, move them, cancel them, cover
 * them. What happens during a shift is the caregiver's to record.
 */
class ShiftController extends Controller
{
    public function __construct(private Scheduler $scheduler, private ShiftActions $actions) {}

    /** Book a weekly pattern, e.g. Mon–Fri 08:00–13:00 for the next four weeks. */
    public function generate(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->refuseIfEnded($assignment);

        $data = $request->validate([
            'weekdays'   => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'from'       => ['required', 'date'],
            'until'      => ['required', 'date', 'after_or_equal:from',
                             'before_or_equal:' . Carbon::parse($request->input('from', 'today'))->addWeeks(26)->toDateString()],
        ], [
            'weekdays.required' => 'Tick at least one day of the week.',
            'end_time.after'    => 'Shifts end after they start. Overnight care is booked as two shifts either side of midnight.',
            'until.before_or_equal' => 'Book at most 26 weeks at a time.',
        ]);

        $result = $this->scheduler->generate($assignment, array_map('intval', $data['weekdays']),
            $data['start_time'], $data['end_time'], $data['from'], $data['until']);

        AuditLog::record($request, 'shifts_generated', 'assignment', $assignment->id, "{$result['created']} shifts");

        $message = $result['created'] . ' ' . str('shift')->plural($result['created']) . ' booked.';
        if ($result['duplicates']) {
            $message .= ' Already booked, skipped: ' . implode(', ', $result['duplicates']) . '.';
        }
        if ($result['clashes']) {
            $message .= ' NOT booked because the caregiver is busy: ' . implode(', ', $result['clashes'])
                . '. Book relief for those days.';
        }

        return back()->with('status', $message);
    }

    /** One extra shift on one day. */
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->refuseIfEnded($assignment);

        $data = $this->validatedTimes($request);

        if (! $assignment->covers(Carbon::parse($data['shift_date']))) {
            throw ValidationException::withMessages(['shift_date' => 'That day is outside the assignment\'s dates.']);
        }
        $this->refuseClash($assignment->caregiver_id, $data);

        $shift = $assignment->shifts()->create($data + ['status' => 'scheduled']);
        AuditLog::record($request, 'created', 'shift', $shift->id, $assignment->patient?->code);

        return back()->with('status', 'Shift booked for ' . $shift->shift_date->format('D j M') . '.');
    }

    public function show(Request $request, Shift $shift): View
    {
        $shift->load(['assignment.patient', 'assignment.caregiver.user', 'assignment.service', 'coveredBy.user', 'visitLog']);
        AuditLog::record($request, 'viewed', 'shift', $shift->id, $shift->assignment?->patient?->code);

        return view('admin.shifts.show', [
            'shift'  => $shift,
            'relief' => $this->actions->reliefOptions($shift),
        ]);
    }

    /** Move a booked shift to another day or time. */
    public function update(Request $request, Shift $shift): RedirectResponse
    {
        return back()->with('status', $this->actions->move($request, $shift));
    }

    public function cancel(Request $request, Shift $shift): RedirectResponse
    {
        return back()->with('status', $this->actions->cancel($request, $shift));
    }

    /** Send a relief caregiver for this one shift, or take the cover off again. */
    public function cover(Request $request, Shift $shift): RedirectResponse
    {
        return back()->with('status', $this->actions->cover($request, $shift));
    }

    /** Correct a visit record (flat phone, forgotten check-out): reason required, audited. */
    public function correct(Request $request, Shift $shift): RedirectResponse
    {
        return back()->with('status', $this->actions->correct($request, $shift));
    }

    /** Nobody came. Recorded, not deleted, because that is what families ask about. */
    public function missed(Request $request, Shift $shift): RedirectResponse
    {
        return back()->with('status', $this->actions->missed($request, $shift));
    }

    private function validatedTimes(Request $request): array
    {
        $data = $request->validate([
            'shift_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
        ], ['end_time.after' => 'The shift must end after it starts.']);

        $data['start_time'] = Shift::normaliseTime($data['start_time']);
        $data['end_time'] = Shift::normaliseTime($data['end_time']);

        return $data;
    }

    private function refuseClash(?int $caregiverId, array $data, ?int $ignore = null, string $field = 'start_time'): void
    {
        if ($caregiverId && ($clash = $this->scheduler->clash($caregiverId, $data['shift_date'], $data['start_time'], $data['end_time'], $ignore))) {
            throw ValidationException::withMessages([
                $field => "That caregiver is already booked {$clash->timeRange()} that day with {$clash->assignment?->patient?->code}.",
            ]);
        }
    }

    private function refuseIfEnded(Assignment $assignment): void
    {
        if ($assignment->status === 'ended') {
            throw ValidationException::withMessages(['shift_date' => 'This assignment has ended. Start a new one to book more shifts.']);
        }
    }
}
