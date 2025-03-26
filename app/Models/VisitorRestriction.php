<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Modelo para gerenciar restrições de visitantes.
 * Este modelo é usado para registrar e gerenciar restrições de acesso para visitantes,
 * permitindo o controle de pessoas marcadas como "Persona Non Grata".
 */
class VisitorRestriction extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_id',
        'name',
        'doc',
        'doc_type_id',
        'reason',
        'severity_level',
        'created_by',
        'active',
        'expires_at'
    ];

    protected $casts = [
        'active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    protected $attributes = [
        'active' => true,
    ];

    /**
     * Retorna o visitante associado à restrição, se houver.
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * Retorna o tipo de documento associado à restrição.
     */
    public function docType(): BelongsTo
    {
        return $this->belongsTo(DocType::class);
    }

    /**
     * Retorna o usuário que criou a restrição.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Escopo para filtrar apenas restrições ativas.
     */
    public function scopeActive(Builder $query): Builder
    {
        Log::info('VisitorRestriction::scopeActive - Início', [
            'query_sql' => $query->toSql(),
            'query_bindings' => $query->getBindings(),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ]);
        
        $now = Carbon::now();
        
        $result = $query->where('active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            });
            
        // Adiciona log detalhado para cada restrição
        $restrictions = $result->get();
        foreach ($restrictions as $restriction) {
            Log::info('VisitorRestriction::scopeActive - Restrição', [
                'id' => $restriction->id,
                'visitor_id' => $restriction->visitor_id,
                'reason' => $restriction->reason,
                'severity' => $restriction->severity_level,
                'active' => $restriction->active ? 'Sim' : 'Não',
                'expires_at' => $restriction->expires_at,
                'is_expired' => $restriction->isExpired() ? 'Sim' : 'Não',
                'is_active' => $restriction->isActive() ? 'Sim' : 'Não',
            ]);
        }
            
        Log::info('VisitorRestriction::scopeActive - Resultado', [
            'result_sql' => $result->toSql(),
            'result_bindings' => $result->getBindings(),
            'count' => $restrictions->count(),
            'now' => $now->toDateTimeString(),
        ]);
        
        return $result;
    }

    /**
     * Verifica se a restrição está expirada.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Verifica se a restrição está ativa.
     */
    public function isActive(): bool
    {
        return $this->active && !$this->isExpired();
    }

    /**
     * Desativa a restrição.
     */
    public function deactivate(): bool
    {
        $this->active = false;
        return $this->save();
    }

    /**
     * Retorna a cor associada ao nível de severidade.
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity_level) {
            'low' => 'warning',
            'medium' => 'orange',
            'high' => 'danger',
            default => 'warning',
        };
    }

    /**
     * Retorna o texto descritivo do nível de severidade.
     */
    public function getSeverityTextAttribute(): string
    {
        return match ($this->severity_level) {
            'low' => 'Baixa',
            'medium' => 'Média',
            'high' => 'Alta',
            default => 'Média',
        };
    }

    /**
     * Boot do modelo - configura eventos.
     */
    protected static function boot()
    {
        parent::boot();

        // Quando uma restrição é salva, atualiza o campo has_restrictions do visitante
        static::saved(function ($restriction) {
            if ($restriction->visitor_id) {
                $restriction->visitor->updateHasRestrictions();
            }
        });

        // Quando uma restrição é deletada, atualiza o campo has_restrictions do visitante
        static::deleted(function ($restriction) {
            if ($restriction->visitor_id) {
                $restriction->visitor->updateHasRestrictions();
            }
        });
    }
} 