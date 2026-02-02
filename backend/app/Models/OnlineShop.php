<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineShop extends Model
{
    protected $fillable = [
        'name',
        'platform',
        'url',
        'api_key',
        'api_secret',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
