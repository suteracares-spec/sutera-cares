<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_ADMIN       = 'admin';
    public const ROLE_COORDINATOR = 'coordinator';
    public const ROLE_CAREGIVER   = 'caregiver';
    public const ROLE_GUARDIAN    = 'guardian';
    public const ROLE_PATIENT     = 'patient';

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'status', 'locale'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'two_factor_secret' => 'encrypted',
        ];
    }

    public function caregiver(): HasOne
    {
        return $this->hasOne(Caregiver::class);
    }

    /** Guardian links. One person may be responsible for more than one patient. */
    public function guardianLinks(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    /** Only set when the patient logs in themselves, which is the minority. */
    public function patientRecord(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    /** Staff see the business; everyone else sees only their own slice of it. */
    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_COORDINATOR], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Where this user lands after signing in. */
    public function homeRoute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN, self::ROLE_COORDINATOR => 'admin.dashboard',
            self::ROLE_CAREGIVER                     => 'caregiver.dashboard',
            self::ROLE_GUARDIAN                      => 'guardian.dashboard',
            default                                  => 'patient.dashboard',
        };
    }
}
