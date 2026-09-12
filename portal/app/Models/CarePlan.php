<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarePlan extends Model
{
    protected $fillable = [
        'patient_id', 'version', 'effective_from', 'effective_to',
        'agreed_by', 'agreed_at', 'author_user_id', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'agreed_at' => 'datetime'];
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CarePlanTask::class);
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
    //
}
