<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Printer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'computer_id'
    ];

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'printer_operator', 'printer_id', 'operator_id')
            ->withTimestamps();
    }
}
