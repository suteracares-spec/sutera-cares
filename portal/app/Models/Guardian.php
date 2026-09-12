<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a family member to a patient, with the access that family member
 * is allowed. Personal-care notes are intimate, families disagree, and an
 * estranged relative should not read them by default.
 */
class Guardian extends Model
{
    protected $fillable = [
        'user_id', 'patient_id', 'relationship', 'is_primary', 'is_bill_payer',
        'can_view_notes', 'can_view_invoices', 'can_request_changes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary'          => 'boolean',
            'is_bill_payer'       => 'boolean',
            'can_view_notes'      => 'boolean',
            'can_view_invoices'   => 'boolean',
            'can_request_changes' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
