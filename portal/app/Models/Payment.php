<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['invoice_id', 'amount', 'method', 'reference', 'paid_on', 'recorded_by_id'];

    public const METHODS = [
        'bank_transfer' => 'Bank transfer',
        'duitnow'       => 'DuitNow',
        'cash'          => 'Cash',
        'cheque'        => 'Cheque',
        'card'          => 'Card',
    ];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
