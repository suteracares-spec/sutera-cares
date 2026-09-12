<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
            'issued_at' => 'datetime', 'subtotal' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
    //
}
