<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Caregiver;
use App\Models\Shift;
use App\Services\Scheduler;
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
    public function __construct(private Scheduler $scheduler) {}

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
            'relief' => Caregiver::with('user')->where('status', 'active')->get()->filter->isPlaceable()
                ->reject(fn ($c) => $c->id === $shift->assignment?->caregiver_id)->sortBy('user.name')->values(),
        ]);
    }

    /** Move a booked shift to another day or time. */
    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $this->refuseUnlessScheduled($shift);

        $data = $this->validatedTimes($request);
        $this->refuseClash($shift->caregiverId(), $data, $shift->id);

        $shift->update($data);
        AuditLog::record($request, 'moved', 'shift', $shift->id, "{$data['shift_date']} {$data['start_time']}–{$data['end_time']}");

        return back()->with('status', 'Shift moved to ' . $shift->shift_date->format('D j M') . ', ' . $shift->timeRange() . '.');
    }

    public function cancel(Request $request, Shift $shift): RedirectResponse
    {
        $this->refuseUnlessScheduled($shift);

        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:300']], [
            'cancel_reason.required' => 'Say why: a cancelled shift with no reason is a dispute waiting to happen.',
        ]);

        $shift->update(['status' => 'cancelled', 'cancel_reason' => $data['cancel_reason']]);
        AuditLog::record($request, 'cancelled', 'shift', $shift->id, $data['cancel_reason']);

        return back()->with('status', 'Shift on ' . $shift->shift_date->format('D j M') . ' cancelled.');
    }

    /** Send a relief caregiver for this one shift, or take the cover off again. */
    public function cover(Request $request, Shift $shift): RedirectResponse
    {
        $this->refuseUnlessScheduled($shift);

        $data = $request->validate([
            'covered_by_id' => ['nullable', Rule::exists('caregivers', 'id')->whereNull('deleted_at')],
        ]);

        $relief = isset($data['covered_by_id']) ? Caregiver::with('user')->find($data['covered_by_id']) : null;

        if ($relief) {
            if (! $relief->isPlaceable()) {
                throw ValidationException::withMessages(['covered_by_id' => "{$relief->user?->name} is not placeable."]);
            }
            $this->refuseClash($relief->id, [
                'shift_date' => $shift->shift_date->toDateString(),
                'start_time' => $shift->start_time,
                'end_time'   => $shift->end_time,
            ], $shift->id, 'covered_by_id');
        }

        $shift->update(['covered_by_id' => $relief?->id]);
        AuditLog::record($request, $relief ? 'covered' : 'cover_removed', 'shift', $shift->id, $relief?->code);

        return back()->with('status', $relief
            ? "{$relief->user?->name} will cover this shift."
            : 'Cover removed. The assigned caregiver is back on this shift.');
    }

    /**
     * The office's correction of a visit record: the phone was flat, the
     * caregiver forgot to check out, the times were wrong. Every
     * correction needs a reason and is audited, because these records are
     * evidence and a silent edit would make them worthless.
     */
    public function correct(Request $request, Shift $shift): RedirectResponse
    {
        abort_if($shift->status === 'cancelled', 422, 'A cancelled shift has no visit to record.');

        $data = $request->validate([
            'check_in_at'  => ['required', 'date'],
            'check_out_at' => ['required', 'date', 'after:check_in_at'],
            'reason'       => ['required', 'string', 'max:300'],
        ], ['reason.required' => 'Say why the record is being corrected.']);

        $in = Carbon::parse($data['check_in_at']);
        $out = Carbon::parse($data['check_out_at']);
        $log = $shift->visitLog;
        $before = $log ? ($log->check_in_at?->format('H:i') . '–' . ($log->check_out_at?->format('H:i') ?? 'open')) : 'no record';

        DB::transaction(function () use ($shift, $log, $in, $out) {
            $fields = ['check_in_at' => $in, 'check_out_at' => $out, 'minutes_worked' => (int) $in->diffInMinutes($out)];

            $log ? $log->update($fields) : $shift->visitLog()->create($fields + ['caregiver_id' => $shift->caregiverId()]);
            $shift->update(['status' => 'completed']);
        });

        AuditLog::record($request, 'visit_corrected', 'shift', $shift->id,
            "{$before} → {$in->format('H:i')}–{$out->format('H:i')}: {$data['reason']}");

        return back()->with('status', 'Visit record corrected. The change and your reason are in the audit log.');
    }

    /** Nobody came. Recorded, not deleted, because that is what families ask about. */
    public function missed(Request $request, Shift $shift): RedirectResponse
    {
        $this->refuseUnlessScheduled($shift);

        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        $shift->update(['status' => 'missed', 'cancel_reason' => $data['reason']]);
        AuditLog::record($request, 'marked_missed', 'shift', $shift->id, $data['reason']);

        return back()->with('status', 'Shift marked as missed.');
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

    private function refuseUnlessScheduled(Shift $shift): void
    {
        if ($shift->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'shift_date' => "This shift is {$shift->status}, so it can no longer be changed here.",
            ]);
        }
    }
}
