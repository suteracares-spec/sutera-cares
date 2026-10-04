<?php

namespace App\Models;

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
}
