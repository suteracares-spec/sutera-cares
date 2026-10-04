<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Who delivers care to whom, for what, from when. The care plan says what
 * the person needs; the assignment says who does it. A one-off booking
 * such as a massage is an assignment that starts and ends on one day.
 */
class Assignment extends Model
{
    protected $fillable = [
        'patient_id', 'caregiver_id', 'care_plan_id', 'service_id', 'role',
        'start_date', 'end_date', 'charge_rate', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'charge_rate' => 'decimal:2'];
    }

    /** Service categories that need an agreed care plan before anyone is sent. */
    public const NEEDS_CARE_PLAN = ['personal_care', 'daily_care', 'live_in'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function isOneOff(): bool
    {
        return $this->end_date !== null && $this->start_date->isSameDay($this->end_date);
    }

    /** What the client pays per unit: the agreed rate, else the list price. */
    public function rate(): ?string
    {
        return $this->charge_rate ?? $this->service?->base_rate;
    }

    /** Whether a date falls inside the assignment. */
    public function covers(\DateTimeInterface $date): bool
    {
        $day = \Illuminate\Support\Carbon::instance($date)->startOfDay();

        return $day->gte($this->start_date) && ($this->end_date === null || $day->lte($this->end_date));
    }
}
