<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class CommonVisitorRestriction extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_id',
        'reason',
        'severity_level',
        'created_by',
        'active',
        'expires_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Níveis de severidade disponíveis
     */
    public const SEVERITY_LEVELS = [
        'none' => 'Nenhuma',
        'low' => 'Baixa',
        'medium' => 'Média',
        'high' => 'Alta',
    ];

    /**
     * Texto de exibição para o nível de severidade
     */
    public function getSeverityTextAttribute(): string
    {
        return self::SEVERITY_LEVELS[$this->severity_level] ?? $this->severity_level;
    }

    /**
     * Inicializa o modelo
     */
    protected static function boot()
    {
        parent::boot();
        
        // Registra o usuário que está criando o registro
        static::creating(function ($model) {
            if (!$model->created_by) {
                $model->created_by = Auth::id();
            }
        });
    }

    /**
     * Relacionamento com o visitante
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * Relacionamento com o tipo de documento
     */
    public function docType(): BelongsTo
    {
        return $this->belongsTo(DocType::class);
    }

    /**
     * Relacionamento com o usuário criador
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Escopo para filtrar apenas restrições ativas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
