<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concern extends Model
{
    protected $fillable = [
        'raised_by_id', 'patient_id', 'shift_id', 'category', 'detail',
        'status', 'assigned_to_id', 'resolution', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
    //
}
