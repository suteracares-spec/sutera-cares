<?php

namespace App\Services;

use App\Models\Caregiver;
use App\Models\Concern;
use App\Models\Shift;
use App\Models\User;
use App\Models\VisitLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The rules of a visit, shared by the caregiver's web page and the
 * Android app, so the two can never disagree about when a caregiver may
 * check in or what a check-out records.
 *
 * Times are passed in rather than read from the clock: the app queues a
 * check-in taken with no signal and sends it later, and the time that
 * matters is when the caregiver tapped, not when the phone found a
 * network. The server's own receipt time is still kept (the row's
 * created_at / updated_at), so both are on record.
 */
class Visits
{
    /** How early a caregiver may check in, and how late after the end. */
    public const EARLY_MINUTES = 60;

    public const LATE_MINUTES = 180;

    /** A queued action older than this is refused: the office should hear about it instead. */
    public const MAX_QUEUE_HOURS = 24;

    /** @return array{today: Collection, coming: Collection, unfinished: Collection} */
    public function shiftsFor(Caregiver $caregiver): array
    {
        $base = fn () => Shift::query()->workedBy($caregiver->id)
            ->with(['assignment.patient', 'assignment.service', 'visitLog'])
            ->orderBy('shift_date')->orderBy('start_time');

        return [
            'today'      => $base()->whereDate('shift_date', today())->where('status', '!=', 'cancelled')->get(),
            'coming'     => $base()->whereDate('shift_date', '>', today())
                                   ->whereDate('shift_date', '<=', today()->addDays(7))
                                   ->where('status', 'scheduled')->get(),
            // A visit left open on an earlier day, so it can be finished.
            'unfinished' => $base()->whereDate('shift_date', '<', today())->where('status', 'in_progress')->get(),
        ];
    }

    /** Why this shift cannot be checked into at that moment, or null if it can. */
    public function checkInProblem(Shift $shift, ?Carbon $at = null): ?string
    {
        $at ??= now();

        return match (true) {
            $shift->status === 'cancelled' => 'This shift was cancelled.',
            $shift->status !== 'scheduled' => $shift->visitLog ? 'Already checked in.' : 'This shift is ' . $shift->status . '.',
            $at->lt($shift->startsAt()->subMinutes(self::EARLY_MINUTES))
                => 'Check-in opens an hour before the shift, at ' . $shift->startsAt()->subMinutes(self::EARLY_MINUTES)->format('H:i') . '.',
            $at->gt($shift->endsAt()->addMinutes(self::LATE_MINUTES))
                => 'This shift ended too long ago to check in. Tell the office what happened.',
            default => null,
        };
    }

    /** A time sent by a phone: not in the future (allowing clock drift), not stale. */
    public function timeProblem(Carbon $at): ?string
    {
        return match (true) {
            $at->gt(now()->addMinutes(5))                    => "The phone's clock is ahead. Check the date and time settings.",
            $at->lt(now()->subHours(self::MAX_QUEUE_HOURS))  => 'This was recorded more than a day ago. Tell the office what happened instead.',
            default                                          => null,
        };
    }

    public function checkIn(Shift $shift, Caregiver $caregiver, Carbon $at, ?float $lat = null, ?float $lng = null): VisitLog
    {
        return DB::transaction(function () use ($shift, $caregiver, $at, $lat, $lng) {
            $log = $shift->visitLog()->create([
                'caregiver_id' => $caregiver->id,
                'check_in_at'  => $at,
                'check_in_lat' => $lat,
                'check_in_lng' => $lng,
            ]);
            $shift->update(['status' => 'in_progress']);

            return $log;
        });
    }

    /** The ids of the care plan tasks a check-out may tick. */
    public function taskIds(Shift $shift): array
    {
        return $shift->assignment->patient->activeCarePlan()?->tasks()->pluck('id')->all() ?? [];
    }

    /**
     * Close the visit. Task wording is stored as it was on the day, not as
     * ids: the plan may be revised later, and the record must say what
     * was done. A flagged concern opens a concern for the office.
     */
    public function checkOut(Shift $shift, User $by, Carbon $at, array $taskIds, string $notes,
                             bool $flagged = false, ?string $category = null, ?string $detail = null): ?Concern
    {
        $log = $shift->visitLog;
        $plan = $shift->assignment->patient->activeCarePlan();
        $done = $plan ? $plan->tasks()->whereIn('id', $taskIds)->pluck('description')->all() : [];

        return DB::transaction(function () use ($shift, $log, $by, $at, $done, $notes, $flagged, $category, $detail) {
            $log->update([
                'check_out_at'    => $at,
                'minutes_worked'  => max(0, (int) $log->check_in_at->diffInMinutes($at)),
                'tasks_completed' => $done,
                'notes'           => $notes,
                'concern_flagged' => $flagged,
                'concern_detail'  => $flagged ? $detail : null,
            ]);
            $shift->update(['status' => 'completed']);

            return $flagged ? Concern::create([
                'raised_by_id' => $by->id,
                'patient_id'   => $shift->assignment->patient_id,
                'shift_id'     => $shift->id,
                'category'     => $category,
                'detail'       => $detail,
                'status'       => 'open',
            ]) : null;
        });
    }
}
