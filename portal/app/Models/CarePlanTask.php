<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarePlanTask extends Model
{
    public $timestamps = false;

    protected $fillable = ['care_plan_id', 'category', 'description', 'frequency', 'time_of_day', 'sort_order'];

    // Labels live here so the form, the plan and (later) the caregiver's
    // phone view all say the same thing.
    public const CATEGORIES = [
        'personal_care' => 'Personal care',
        'mobility'      => 'Mobility',
        'household'     => 'Household',
        'companionship' => 'Companionship',
        'appointment'   => 'Appointments',
        'wellness'      => 'Wellness',
    ];

    public const FREQUENCIES = [
        'every_visit' => 'Every visit',
        'daily'       => 'Daily',
        'weekly'      => 'Weekly',
        'as_needed'   => 'As needed',
    ];

    public const TIMES = [
        'any'     => 'Any time',
        'morning' => 'Morning',
        'midday'  => 'Midday',
        'evening' => 'Evening',
        'night'   => 'Night',
    ];
}
