<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['invoice_id', 'shift_id', 'service_id', 'description', 'quantity', 'rate', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
