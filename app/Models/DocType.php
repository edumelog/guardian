<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocType extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($docType) {
            if ($docType->is_default) {
                // Remove o default de outros registros
                static::where('id', '!=', $docType->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }
}
