<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'max_visitors',
        'parent_id'
    ];

    protected $attributes = [
        'max_visitors' => 0,
        'address' => '',
        'phone' => ''
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Destination::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Destination::class, 'parent_id');
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function getAllChildrenIds(): array
    {
        $ids = [];
        $this->appendChildrenIds($this, $ids);
        return $ids;
    }

    private function appendChildrenIds(Destination $destination, array &$ids): void
    {
        foreach ($destination->children as $child) {
            $ids[] = $child->id;
            $this->appendChildrenIds($child, $ids);
        }
    }

    public function getAllAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;
        
        while ($current) {
            $ancestors[] = $current->id;
            $current = $current->parent;
        }
        
        return $ancestors;
    }
}
