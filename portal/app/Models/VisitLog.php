<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
            'check_in_at' => 'datetime', 'check_out_at' => 'datetime',
            'tasks_completed' => 'array', 'concern_flagged' => 'boolean',
        ];
    }

    public function shift(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
    //
}
