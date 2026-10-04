<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something someone wants a human to look at: a fall a caregiver noticed,
 * a family unhappy with a visit, a request to change the care plan.
 * Anyone can raise one; the office owns it until it is resolved.
 */
class Concern extends Model
{
    protected $fillable = [
        'raised_by_id', 'patient_id', 'shift_id', 'category', 'detail',
        'status', 'assigned_to_id', 'resolution', 'resolved_at',
    ];

    public const CATEGORIES = [
        'safety'        => 'Safety or a fall',
        'care_quality'  => 'Quality of care',
        'attendance'    => 'Lateness or a missed visit',
        'staff_conduct' => 'A caregiver\'s conduct',
        'billing'       => 'Billing',
        'other'         => 'Something else',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            // Often describes a person's health or an incident in their home.
            'detail'      => 'encrypted',
            'resolution'  => 'encrypted',
        ];
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'investigating'], true);
    }
}
