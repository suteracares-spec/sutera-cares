<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Shift extends Model
{
    protected $fillable = [
        'assignment_id', 'shift_date', 'start_time', 'end_time',
        'status', 'cancel_reason', 'covered_by_id',
    ];

    protected function casts(): array
    {
        return ['shift_date' => 'date'];
    }

    // Times are stored as HH:MM:SS whatever the form sent, so that string
    // comparison in the overlap check compares like with like.
    protected function startTime(): Attribute
    {
        return Attribute::set(fn ($value) => self::normaliseTime($value));
    }

    protected function endTime(): Attribute
    {
        return Attribute::set(fn ($value) => self::normaliseTime($value));
    }

    public static function normaliseTime(?string $value): ?string
    {
        return $value === null ? null : Carbon::createFromFormat('H:i', substr($value, 0, 5))->format('H:i:s');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function visitLog(): HasOne
    {
        return $this->hasOne(VisitLog::class);
    }

    public function coveredBy(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'covered_by_id');
    }

    /** The caregiver actually doing this shift: relief cover if any, else the assigned one. */
    public function caregiverId(): ?int
    {
        return $this->covered_by_id ?? $this->assignment?->caregiver_id;
    }

    public function caregiver(): ?Caregiver
    {
        return $this->covered_by_id ? $this->coveredBy : $this->assignment?->caregiver;
    }

    public function startsAt(): Carbon
    {
        return $this->shift_date->copy()->setTimeFromTimeString($this->start_time);
    }

    public function endsAt(): Carbon
    {
        return $this->shift_date->copy()->setTimeFromTimeString($this->end_time);
    }

    public function scheduledMinutes(): int
    {
        return (int) $this->startsAt()->diffInMinutes($this->endsAt());
    }

    /** "08:00–13:00" */
    public function timeRange(): string
    {
        return substr($this->start_time, 0, 5) . '–' . substr($this->end_time, 0, 5);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['scheduled', 'in_progress'], true);
    }

    /** Shifts a caregiver is doing: their own uncovered ones, plus any they cover. */
    public function scopeWorkedBy(Builder $query, int $caregiverId): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('covered_by_id', $caregiverId)
            ->orWhere(fn ($q) => $q->whereNull('covered_by_id')
                ->whereHas('assignment', fn ($a) => $a->where('caregiver_id', $caregiverId))));
    }

    /** Live shifts that overlap a time window on a date. */
    public function scopeOverlapping(Builder $query, string $date, string $start, string $end): Builder
    {
        return $query->whereDate('shift_date', $date)
            ->whereNotIn('status', ['cancelled', 'missed'])
            ->where('start_time', '<', self::normaliseTime($end))
            ->where('end_time', '>', self::normaliseTime($start));
    }
}
