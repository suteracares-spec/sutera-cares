<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An invoice is built from shifts that actually happened, so every line
 * can be explained: this day, this time, this service, this rate.
 *
 * Draft: editable, numbered DRAFT-n. Issued ("sent"): numbered
 * INV-2026-0001, no longer editable; it can be paid or voided, never
 * deleted. "Overdue" is worked out from the due date rather than stored,
 * so it can never be stale.
 */
class Invoice extends Model
{
    protected $fillable = [
        'number', 'patient_id', 'bill_to_id', 'period_start', 'period_end',
        'subtotal', 'surcharges', 'total', 'amount_paid', 'currency',
        'due_date', 'status', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date',
            'issued_at'    => 'datetime',
            'subtotal'     => 'decimal:2', 'surcharges' => 'decimal:2',
            'total'        => 'decimal:2', 'amount_paid' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function billTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bill_to_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderByRaw('shift_id is null')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['sent', 'part_paid', 'overdue'], true)
            && $this->due_date?->isBefore(today());
    }

    /** What to call it on screen, overdue included. */
    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public function statusLabel(): string
    {
        return ['draft' => 'Draft', 'sent' => 'Unpaid', 'part_paid' => 'Part paid', 'paid' => 'Paid',
                'overdue' => 'Overdue', 'void' => 'Void'][$this->displayStatus()];
    }

    /** Issued and not fully paid. */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['sent', 'part_paid', 'overdue']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()->whereDate('due_date', '<', today());
    }

    /** Recompute the totals from the lines. Shift lines are the subtotal; manual lines are adjustments. */
    public function recalculate(): void
    {
        $lines = $this->lines()->get();
        $subtotal = round((float) $lines->whereNotNull('shift_id')->sum('amount'), 2);
        $adjustments = round((float) $lines->whereNull('shift_id')->sum('amount'), 2);

        $this->update(['subtotal' => $subtotal, 'surcharges' => $adjustments, 'total' => $subtotal + $adjustments]);
    }

    /** INV-2026-0001, counting this year's issued invoices. */
    public static function nextNumber(): string
    {
        $prefix = 'INV-' . now()->year . '-';
        $last = static::where('number', 'like', $prefix . '%')->orderByDesc('number')->value('number');

        return $prefix . str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 4, '0', STR_PAD_LEFT);
    }
}
