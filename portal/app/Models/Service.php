<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = ['code', 'name', 'category', 'unit', 'base_rate', 'active'];

    protected function casts(): array
    {
        return ['base_rate' => 'decimal:2', 'active' => 'boolean'];
    }
    //
}
