<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;

/**
 * Modelo para registro de ocorrências de segurança
 * 
 * Este modelo representa um registro de ocorrência de segurança,
 * podendo ter diferentes níveis de severidade e estando opcionalmente
 * vinculado a visitantes e/ou destinos.
 */
class Occurrence extends Model
{
    use HasFactory;
    
    /**
     * Atributos que podem ser preenchidos em massa
     */
    protected $fillable = [
        'description',
        'severity',
        'occurrence_datetime',
        'created_by',
        'updated_by',
        'is_editable',
    ];
    
    /**
     * Conversão de tipos para atributos
     */
    protected $casts = [
        'occurrence_datetime' => 'datetime',
        'is_editable' => 'boolean',
    ];
    
    /**
     * Mapeia os valores de severidade para cores/nível
     */
    const SEVERITY_LEVELS = [
        'gray' => 'Nenhuma (Apenas Informativa)',
        'green' => 'Baixa',
        'amber' => 'Média',
        'red' => 'Alta',
    ];
    
    /**
     * Retorna o nome do nível de severidade
     */
    public function getSeverityNameAttribute(): string
    {
        return self::SEVERITY_LEVELS[$this->severity] ?? 'Desconhecido';
    }
    
    /**
     * Retorna a cor CSS correspondente à severidade
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'gray' => 'gray',
            'green' => 'success',
            'amber' => 'warning',
            'red' => 'danger',
            default => 'gray'
        };
    }
    
    /**
     * Boot do modelo - configura eventos
     */
    protected static function boot()
    {
        parent::boot();
        
        // Registra o usuário que está criando o registro
        static::creating(function ($model) {
            $model->created_by = Auth::id();
            // Sempre define a data/hora da ocorrência como a atual
            $model->occurrence_datetime = now();
        });
        
        // Registra o usuário que está atualizando o registro
        static::updating(function ($model) {
            $model->updated_by = Auth::id();
            // Ao atualizar, não permite alterar a data/hora original
            $model->occurrence_datetime = $model->getOriginal('occurrence_datetime');
        });
    }
    
    /**
     * Relacionamento com visitantes vinculados à ocorrência
     */
    public function visitors(): BelongsToMany
    {
        return $this->belongsToMany(Visitor::class, 'occurrence_visitor')
            ->withTimestamps();
    }
    
    /**
     * Relacionamento com destinos vinculados à ocorrência
     */
    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(Destination::class, 'occurrence_destination')
            ->withTimestamps();
    }
    
    /**
     * Relacionamento com o usuário criador
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Relacionamento com o usuário que atualizou
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
