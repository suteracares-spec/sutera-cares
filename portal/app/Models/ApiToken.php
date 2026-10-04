<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A signed-in phone. The app holds the token; the database holds only
 * its SHA-256 hash. Tokens last 180 days, and are revoked by signing out,
 * by suspension, and whenever the password changes.
 */
class ApiToken extends Model
{
    public const LIFETIME_DAYS = 180;

    protected $fillable = ['user_id', 'token_hash', 'device', 'last_used_at', 'expires_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Issue a token; the plain value is returned once and never stored. */
    public static function issue(User $user, ?string $device): string
    {
        $plain = 'sutera_' . Str::random(48);

        static::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $plain),
            'device'     => $device ? mb_substr($device, 0, 100) : null,
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
        ]);

        return $plain;
    }

    public static function findValid(?string $plain): ?self
    {
        if (! $plain) {
            return null;
        }

        return static::with('user')->where('token_hash', hash('sha256', $plain))
            ->where('expires_at', '>', now())->first();
    }

    /** Sign the user out of every phone. */
    public static function revokeAll(User $user): void
    {
        static::where('user_id', $user->id)->delete();
    }
}
