<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['invoice_id', 'shift_id', 'service_id', 'description', 'quantity', 'rate', 'amount'];
    //
}
