<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocType extends Model
{
    use HasFactory;

    protected $fillable = [
        'type'
    ];

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }
}
