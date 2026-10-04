<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What happened on a shift: when the caregiver arrived and left, where
 * they checked in, what they did and what they noticed. The evidence a
 * family or the office reaches for in a dispute.
 */
class VisitLog extends Model
{
    protected $fillable = [
        'shift_id', 'caregiver_id', 'check_in_at', 'check_out_at',
        'check_in_lat', 'check_in_lng', 'tasks_completed', 'notes',
        'concern_flagged', 'concern_detail', 'minutes_worked',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at'     => 'datetime',
            'check_out_at'    => 'datetime',
            'tasks_completed' => 'array',
            'concern_flagged' => 'boolean',
            // Visit notes describe personal care: health information.
            'notes'           => 'encrypted',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
