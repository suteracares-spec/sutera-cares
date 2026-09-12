<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A dated row, not a recurrence rule. Real life is full of exceptions —
 * a public holiday, a hospital admission, a caregiver off sick — and you
 * cannot cancel a single day of a rule.
 */
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
}
