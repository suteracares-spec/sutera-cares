<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'area', 'role_applied', 'years_experience',
        'availability', 'languages', 'notes', 'status', 'caregiver_id',
    ];
    //
}
