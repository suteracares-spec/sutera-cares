<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    protected $fillable = [
        'client_name', 'client_phone', 'client_email', 'client_relationship',
        'patient_name', 'patient_age', 'patient_area', 'patient_mobility',
        'needs', 'schedule_wanted', 'source', 'status', 'patient_id',
    ];

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
    //
}
