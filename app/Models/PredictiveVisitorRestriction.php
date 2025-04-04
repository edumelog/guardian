<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictiveVisitorRestriction extends Model
{
    use HasFactory;

    /**
     * Os atributos que são atribuíveis em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name_pattern',
        'any_document_type',
        'document_types',
        'document_number_pattern',
        'any_destination',
        'destinations',
        'reason',
        'severity_level',
        'created_by',
        'active',
        'expires_at',
        'auto_occurrence',
    ];

    /**
     * Os atributos que devem ser convertidos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'document_types' => 'array',
        'destinations' => 'array',
        'any_document_type' => 'boolean',
        'any_destination' => 'boolean',
        'active' => 'boolean',
        'auto_occurrence' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Obtém o usuário que criou esta restrição.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Verifica se a restrição está expirada.
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return $this->expires_at->isPast();
    }

    /**
     * Escopo de consulta para obter apenas restrições ativas.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Obtém a descrição da restrição para uso no sistema.
     * 
     * @return string
     */
    public function getDescription(): string
    {
        $desc = "Restrição Preditiva";
        
        if ($this->name_pattern) {
            $desc .= " - Nome: {$this->name_pattern}";
        }
        
        if (!$this->any_document_type && !empty($this->document_types)) {
            $desc .= " - Documentos específicos";
        }
        
        if ($this->document_number_pattern) {
            $desc .= " - Doc #: {$this->document_number_pattern}";
        }
        
        return $desc;
    }
}
