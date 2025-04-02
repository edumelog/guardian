<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class PredictiveVisitorRestriction extends Model
{
    use HasFactory;

    protected $fillable = [
        'partial_name',
        'partial_doc',
        'doc_type_id',
        'phone',
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

    /**
     * Relacionamento com o tipo de documento
     */
    public function docType(): BelongsTo
    {
        return $this->belongsTo(DocType::class);
    }

    /**
     * Relacionamento com o usuário que criou a restrição
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope para restringir apenas restrições ativas
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Desativa a restrição
     */
    public function deactivate(): bool
    {
        $this->active = false;
        return $this->save();
    }

    /**
     * Converte padrões com wildcards (* e ?) para regex seguindo as regras específicas
     * - * (asterisco): representa qualquer quantidade de caracteres (inclusive zero)
     * - ? (interrogação): representa exatamente um caractere
     */
    protected function patternToRegex(string $pattern): string
    {
        // Escapar caracteres especiais do regex, exceto * e ?
        $escapedPattern = preg_quote($pattern, '/');
        
        // Reverter o escape dos * e ? que queremos processar
        $escapedPattern = str_replace(['\*', '\?'], ['*', '?'], $escapedPattern);
        
        // Converter * para .* (qualquer quantidade de caracteres)
        $escapedPattern = str_replace('*', '.*', $escapedPattern);
        
        // Converter ? para . (exatamente um caractere)
        $escapedPattern = str_replace('?', '.', $escapedPattern);
        
        // Aplicar âncoras de início e fim apenas se o padrão não começa ou termina com *
        $needsStartAnchor = !str_starts_with($pattern, '*');
        $needsEndAnchor = !str_ends_with($pattern, '*');
        
        $finalPattern = '/';
        if ($needsStartAnchor) {
            $finalPattern .= '^';
        }
        
        $finalPattern .= $escapedPattern;
        
        if ($needsEndAnchor) {
            $finalPattern .= '$';
        }
        
        $finalPattern .= '/i'; // case insensitive
        
        Log::info('PredictiveVisitorRestriction: Conversão de wildcard para regex', [
            'original' => $pattern,
            'final' => $finalPattern
        ]);
        
        return $finalPattern;
    }

    /**
     * Verifica se um visitante corresponde aos critérios desta restrição
     */
    public function matchesVisitor(Visitor $visitor): bool
    {
        $matches = true;
        
        // Verifica tipo de documento se especificado
        if ($this->doc_type_id && $visitor->doc_type_id != $this->doc_type_id) {
            $matches = false;
        }
        
        // Verifica documento se especificado
        if ($matches && $this->partial_doc) {
            $pattern = $this->patternToRegex($this->partial_doc);
            if (!preg_match($pattern, $visitor->doc)) {
                $matches = false;
            }
        }
        
        // Verifica nome se especificado
        if ($matches && $this->partial_name) {
            $pattern = $this->patternToRegex($this->partial_name);
            if (!preg_match($pattern, $visitor->name)) {
                $matches = false;
            }
        }
        
        // Verifica telefone se especificado
        if ($matches && $this->phone && $visitor->phone) {
            $pattern = $this->patternToRegex($this->phone);
            if (!preg_match($pattern, $visitor->phone)) {
                $matches = false;
            }
        }
        
        Log::info('PredictiveVisitorRestriction: Resultado da verificação', [
            'visitor_id' => $visitor->id,
            'visitor_name' => $visitor->name,
            'restriction_id' => $this->id,
            'partial_name' => $this->partial_name,
            'matches' => $matches
        ]);
        
        return $matches;
    }

    /**
     * Encontra restrições ativas que correspondam ao visitante fornecido
     */
    public static function findMatchingRestrictions(Visitor $visitor): \Illuminate\Database\Eloquent\Collection
    {
        $restrictions = self::active()
            ->with('docType', 'creator')
            ->get()
            ->filter(function ($restriction) use ($visitor) {
                return $restriction->matchesVisitor($visitor);
            });
            
        Log::info('PredictiveVisitorRestriction::findMatchingRestrictions', [
            'visitor_id' => $visitor->id,
            'visitor_name' => $visitor->name,
            'visitor_doc' => $visitor->doc,
            'matching_restrictions_count' => $restrictions->count(),
        ]);
        
        return $restrictions;
    }

    /**
     * Mutator para garantir que o nome parcial seja salvo em maiúsculas
     */
    public function setPartialNameAttribute($value)
    {
        $this->attributes['partial_name'] = $value ? mb_strtoupper($value) : null;
    }

    /**
     * Mutator para garantir que o documento parcial seja salvo em maiúsculas
     */
    public function setPartialDocAttribute($value)
    {
        $this->attributes['partial_doc'] = $value ? mb_strtoupper($value) : null;
    }
} 