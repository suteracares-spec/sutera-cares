<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'user_id', 'name', 'ic_number', 'dob', 'gender', 'address', 'area',
        'postcode', 'mobility_level', 'languages', 'allergies', 'notes',
        'consent_given_at', 'consent_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'dob'              => 'date',
            'consent_given_at' => 'datetime',
            // Identity numbers and health notes are sensitive personal data
            // under the PDPA. Encrypted so a database dump taken without the
            // application key is useless to whoever has it.
            'ic_number'        => 'encrypted',
            'notes'            => 'encrypted',
        ];
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activeCarePlan(): ?CarePlan
    {
        return $this->carePlans()->where('status', 'active')->latest('version')->first();
    }

    public function hasConsent(): bool
    {
        return $this->consent_given_at !== null;
    }

    /** Next free reference, e.g. SCP-0042. */
    public static function nextCode(): string
    {
        return sprintf('SCP-%04d', (static::withTrashed()->max('id') ?? 0) + 1);
    }
}
