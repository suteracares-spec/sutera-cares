<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a person needs, versioned. A draft is edited freely; an active plan
 * is never edited, only revised into a new draft, so "what had we agreed
 * on the 3rd of March" always has an answer.
 */
class CarePlan extends Model
{
    protected $fillable = [
        'patient_id', 'version', 'effective_from', 'effective_to',
        'agreed_by', 'agreed_at', 'author_user_id', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to'   => 'date',
            'agreed_at'      => 'datetime',
            // Free text about a person's care is health information.
            'notes'          => 'encrypted',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CarePlanTask::class)->orderBy('sort_order')->orderBy('id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** The status pill's colour class in portal.css. */
    public function pillClass(): string
    {
        return ['draft' => 'new', 'active' => 'active', 'superseded' => 'closed'][$this->status] ?? '';
    }
}
