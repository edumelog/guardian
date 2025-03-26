<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class PartialVisitorRestriction extends Model
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
     * Converte wildcards (* e ?) para o padrão SQL (% e _)
     */
    protected static function convertWildcardsToSql(string $term): string
    {
        $sqlTerm = str_replace(['*', '?'], ['%', '_'], $term);
        return $sqlTerm;
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
            $pattern = '/^' . str_replace(['*', '?'], ['.*', '.'], $this->partial_doc) . '$/i';
            if (!preg_match($pattern, $visitor->doc)) {
                $matches = false;
            }
        }
        
        // Verifica nome se especificado
        if ($matches && $this->partial_name) {
            $pattern = '/^' . str_replace(['*', '?'], ['.*', '.'], $this->partial_name) . '$/i';
            if (!preg_match($pattern, $visitor->name)) {
                $matches = false;
            }
        }
        
        // Verifica telefone se especificado
        if ($matches && $this->phone && $visitor->phone) {
            $pattern = '/^' . str_replace(['*', '?'], ['.*', '.'], $this->phone) . '$/i';
            if (!preg_match($pattern, $visitor->phone)) {
                $matches = false;
            }
        }
        
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
            
        Log::info('PartialVisitorRestriction::findMatchingRestrictions', [
            'visitor_id' => $visitor->id,
            'visitor_name' => $visitor->name,
            'visitor_doc' => $visitor->doc,
            'matching_restrictions_count' => $restrictions->count(),
        ]);
        
        return $restrictions;
    }
}
