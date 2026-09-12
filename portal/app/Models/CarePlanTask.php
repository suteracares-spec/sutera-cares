<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarePlanTask extends Model
{
    public $timestamps = false;

    protected $fillable = ['care_plan_id', 'category', 'description', 'frequency', 'time_of_day', 'sort_order'];
    //
}
