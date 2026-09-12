<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caregiver extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'code', 'ic_number', 'gender', 'dob', 'languages', 'skills',
        'has_own_transport', 'max_travel_km', 'base_area', 'hourly_rate',
        'police_check_expires_at', 'right_to_work_verified', 'status',
    ];

    protected function casts(): array
    {
        return [
            'dob'                     => 'date',
            'police_check_expires_at' => 'date',
            'has_own_transport'       => 'boolean',
            'right_to_work_verified'  => 'boolean',
            'hourly_rate'             => 'decimal:2',
            'ic_number'               => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** Placeable only when vetting is complete and still current. */
    public function isPlaceable(): bool
    {
        return $this->status === 'active'
            && $this->right_to_work_verified
            && $this->police_check_expires_at !== null
            && $this->police_check_expires_at->isFuture();
    }

    /** Warn before a check lapses, not after. */
    public function policeCheckExpiringSoon(int $days = 60): bool
    {
        return $this->police_check_expires_at !== null
            && $this->police_check_expires_at->isBefore(now()->addDays($days));
    }

    public static function nextCode(): string
    {
        return sprintf('CG-%03d', (static::withTrashed()->max('id') ?? 0) + 1);
    }
}
