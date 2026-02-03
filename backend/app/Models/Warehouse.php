<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = [
        'code',
        'name',
        'address',
        'timezone',
        'is_active',
        'can_accept_returns',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_accept_returns' => 'boolean',
    ];
}
