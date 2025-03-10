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
        'alias',
        'address',
        'phone',
        'max_visitors',
        'parent_id',
        'is_active'
    ];

    protected $attributes = [
        'max_visitors' => 0,
        'address' => '',
        'phone' => '',
        'is_active' => true
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function parent()
    {
        return $this->belongsTo(Destination::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Destination::class, 'parent_id');
    }

    public function visitors()
    {
        return $this->hasMany(Visitor::class);
    }

    public function visitorLogs()
    {
        return $this->hasMany(\App\Models\VisitorLog::class);
    }

    /**
     * Retorna um array com os IDs de todos os destinos filhos (recursivamente)
     */
    public function getAllChildrenIds(): array
    {
        $ids = [];
        
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllChildrenIds());
        }
        
        return $ids;
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

    /**
     * Verifica se este destino ou qualquer um de seus filhos (recursivamente) tem visitas
     */
    public function hasVisitsInHierarchy(): bool
    {
        // Verifica se o destino atual tem visitas
        if ($this->visitorLogs()->exists()) {
            return true;
        }

        // Verifica recursivamente os filhos
        foreach ($this->children as $child) {
            if ($child->hasVisitsInHierarchy()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna o primeiro alias disponível na hierarquia de destinos
     */
    public function getFirstAvailableAlias(): ?string
    {
        if (!empty($this->alias)) {
            return $this->alias;
        }

        $current = $this->parent;
        while ($current) {
            if (!empty($current->alias)) {
                return $current->alias;
            }
            $current = $current->parent;
        }

        return null;
    }

    public function getCurrentVisitorsCount(): int
    {
        return $this->visitorLogs()
            ->where('destination_id', $this->id)
            ->whereNull('out_date')
            ->whereNotNull('in_date')
            ->where('in_date', '<=', now())
            ->distinct('visitor_id')
            ->count();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
