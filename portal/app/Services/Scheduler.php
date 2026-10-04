<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Shift;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns a weekly pattern into dated shift rows, and keeps one caregiver
 * from being in two homes at once.
 *
 * Shifts are rows, not a rule, because real weeks have exceptions: a
 * public holiday, a hospital stay, a caregiver off sick. A single row can
 * be cancelled or covered; one day of a recurrence rule cannot.
 */
class Scheduler
{
    /** Hard ceiling on one generation run, so a typo cannot create years of shifts. */
    public const MAX_SHIFTS = 400;

    /** The live shift this caregiver already has at that time, if any. */
    public function clash(int $caregiverId, string $date, string $start, string $end, ?int $ignoreShiftId = null): ?Shift
    {
        return Shift::query()
            ->workedBy($caregiverId)
            ->overlapping($date, $start, $end)
            ->when($ignoreShiftId, fn ($q) => $q->whereKeyNot($ignoreShiftId))
            ->with('assignment.patient')
            ->first();
    }

    /**
     * Create shifts on the chosen weekdays between two dates, inside the
     * assignment's own dates. Skips a day that already has this shift, and
     * a day the caregiver is already booked elsewhere, and says which.
     *
     * @param  list<int>  $weekdays  ISO days, 1 = Monday … 7 = Sunday
     * @return array{created: int, duplicates: list<string>, clashes: list<string>}
     */
    public function generate(Assignment $assignment, array $weekdays, string $start, string $end,
                             string $from, string $until): array
    {
        $start = Shift::normaliseTime($start);
        $end = Shift::normaliseTime($end);
        $result = ['created' => 0, 'duplicates' => [], 'clashes' => []];

        DB::transaction(function () use ($assignment, $weekdays, $start, $end, $from, $until, &$result) {
            foreach (CarbonPeriod::create($from, $until) as $day) {
                if (! in_array($day->isoWeekday(), $weekdays, true) || ! $assignment->covers($day)) {
                    continue;
                }

                $date = $day->toDateString();
                $label = $day->format('D j M');

                $exists = $assignment->shifts()->whereDate('shift_date', $date)
                    ->where('start_time', $start)->where('status', '!=', 'cancelled')->exists();
                if ($exists) {
                    $result['duplicates'][] = $label;
                    continue;
                }

                if ($other = $this->clash($assignment->caregiver_id, $date, $start, $end)) {
                    $result['clashes'][] = $label . ' (with ' . $other->assignment?->patient?->code . ')';
                    continue;
                }

                if ($result['created'] >= self::MAX_SHIFTS) {
                    break;
                }

                $assignment->shifts()->create([
                    'shift_date' => $date,
                    'start_time' => $start,
                    'end_time'   => $end,
                    'status'     => 'scheduled',
                ]);
                $result['created']++;
            }
        });

        return $result;
    }

    /** End an assignment and cancel what was booked after its last day. */
    public function end(Assignment $assignment, Carbon $lastDay, string $reason): int
    {
        return DB::transaction(function () use ($assignment, $lastDay, $reason) {
            $assignment->update(['end_date' => $lastDay->toDateString(), 'status' => 'ended']);

            return $assignment->shifts()
                ->whereDate('shift_date', '>', $lastDay->toDateString())
                ->where('status', 'scheduled')
                ->update(['status' => 'cancelled', 'cancel_reason' => mb_substr($reason, 0, 300)]);
        });
    }
}
