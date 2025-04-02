<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomaticOccurrence extends Model
{
    protected $fillable = [
        'key',
        'title',
        'description',
        'enabled'
    ];

    protected $casts = [
        'enabled' => 'boolean'
    ];
} 