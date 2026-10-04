<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Append-only. PDPA requires knowing who looked at what, so reads are
 * recorded here as well as writes. Nothing in the application updates or
 * deletes these rows.
 */
class AuditLog extends Model
{
    protected $table = 'audit_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id',
        'detail', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * Stamp the time here, in the app's time zone, rather than leave it to
     * the database's default: database clocks run in UTC (or the host's
     * zone), which put every entry hours out.
     */
    protected static function booted(): void
    {
        static::creating(fn (self $entry) => $entry->created_at ??= now());
    }

    /** Write one entry for the current request. */
    public static function record(Request $request, string $action, ?string $subjectType = null,
                                  ?int $subjectId = null, ?string $detail = null): self
    {
        return static::create([
            'user_id'      => $request->user()?->id,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'detail'       => $detail === null ? null : mb_substr($detail, 0, 500),
            'ip_address'   => $request->ip(),
            'user_agent'   => mb_substr((string) $request->userAgent(), 0, 300),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Narrow by ?user=, ?action= and ?subject=patient:12 (one record's history). */
    public function scopeFilter(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->integer('user'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->string('action')->toString(), fn ($q, $a) => $q->where('action', $a))
            ->when($request->string('subject')->toString(), function ($q, $subject) {
                [$type, $id] = array_pad(explode(':', $subject, 2), 2, null);
                $q->where('subject_type', $type)->when($id, fn ($q) => $q->where('subject_id', (int) $id));
            });
    }
}
