<?php

namespace App\Services;

use App\Models\Visitor;
use App\Models\Occurrence;
use App\Models\CommonVisitorRestriction;
use App\Models\PredictiveVisitorRestriction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OccurrenceService
{
    /**
     * Registra uma ocorrência de saída para um visitante com restrições
     * 
     * @param Visitor $visitor O visitante
     * @param mixed $lastLog O último log de visita do visitante
     * @return void
     */
    public function registerExitOccurrence(Visitor $visitor, $lastLog): void
    {
        // Verifica se o visitante tem restrições ativas
        $activeRestrictions = $visitor->getAllActiveRestrictions();
        
        // Se não houver restrições ativas, não precisa registrar ocorrência
        if ($activeRestrictions->isEmpty()) {
            return;
        }
        
        // Filtra apenas restrições com auto_occurrence habilitado
        $restrictionsWithAutoOccurrence = $activeRestrictions->filter(function ($restriction) {
            return $restriction->auto_occurrence;
        });
        
        // Se não houver restrições com auto_occurrence, não registra
        if ($restrictionsWithAutoOccurrence->isEmpty()) {
            return;
        }
        
        // Obtém a restrição mais crítica para determinar a severidade
        $highestSeverity = $this->getHighestSeverityLevel($restrictionsWithAutoOccurrence);
        
        // Prepara a descrição da ocorrência
        $description = $this->prepareExitOccurrenceDescription($visitor, $lastLog, $restrictionsWithAutoOccurrence);
        
        // Cria a ocorrência
        $occurrence = Occurrence::create([
            'description' => $description,
            'severity' => $this->mapSeverityLevel($highestSeverity),
            'occurrence_datetime' => now(),
            'created_by' => Auth::id(),
            'updated_by' => null,
            'is_editable' => false,
        ]);
        
        // Vincula o visitante à ocorrência
        $occurrence->visitors()->attach($visitor->id);
        
        // Vincula o destino à ocorrência
        if ($lastLog && $lastLog->destination_id) {
            $occurrence->destinations()->attach($lastLog->destination_id);
        }
        
        Log::info('[Ocorrência Automática - Saída] Ocorrência registrada com sucesso', [
            'occurrence_id' => $occurrence->id,
            'visitor_id' => $visitor->id,
            'total_restrictions' => $restrictionsWithAutoOccurrence->count(),
            'restrictions' => $restrictionsWithAutoOccurrence->map(fn($r) => [
                'id' => $r->id ?? null,
                'reason' => $r->reason,
                'severity' => $r->severity_level,
                'type' => isset($r->is_predictive) && $r->is_predictive ? 'Preditiva' : 'Comum'
            ])->toArray()
        ]);
    }
    
    /**
     * Obtém o nível de severidade mais alto entre as restrições
     * 
     * @param \Illuminate\Support\Collection $restrictions
     * @return string
     */
    private function getHighestSeverityLevel($restrictions): string
    {
        $severityLevels = [
            'none' => 0,
            'low' => 1,
            'medium' => 2,
            'high' => 3
        ];
        
        $highestLevel = 'none';
        $highestValue = 0;
        
        foreach ($restrictions as $restriction) {
            $currentValue = $severityLevels[$restriction->severity_level] ?? 0;
            if ($currentValue > $highestValue) {
                $highestValue = $currentValue;
                $highestLevel = $restriction->severity_level;
            }
        }
        
        return $highestLevel;
    }
    
    /**
     * Mapeia o nível de severidade para o valor usado nas ocorrências
     * 
     * @param string $severityLevel
     * @return string
     */
    private function mapSeverityLevel(string $severityLevel): string
    {
        return match ($severityLevel) {
            'none' => 'gray',
            'low' => 'green',
            'medium' => 'amber',
            'high' => 'red',
            default => 'gray',
        };
    }
    
    /**
     * Prepara a descrição da ocorrência de saída
     * 
     * @param Visitor $visitor
     * @param mixed $lastLog
     * @param \Illuminate\Support\Collection $restrictions
     * @return string
     */
    private function prepareExitOccurrenceDescription(Visitor $visitor, $lastLog, $restrictions): string
    {
        $docTypeName = $visitor->docType->type ?? 'Desconhecido';
        $destinationName = $lastLog->destination->name ?? 'Não informado';
        
        $description = "Registro de saída de visitante com múltiplas Restrições de Acesso:

Dados do visitante:
Nome: " . $visitor->name . "
Documento: " . $visitor->doc . " (" . $docTypeName . ")
Telefone: " . ($visitor->phone ?? 'N/A') . "
Destino: " . $destinationName . "

Detalhes da visita:
Entrada: " . ($lastLog->in_date ? $lastLog->in_date->format('d/m/Y H:i:s') : 'N/A') . "
Saída: " . ($lastLog->out_date ? $lastLog->out_date->format('d/m/Y H:i:s') : 'N/A') . "

Restrições ativas (" . $restrictions->count() . "):";

        // Agrupa as restrições por severidade
        $restrictionsBySeverity = $restrictions->groupBy('severity_level');
        
        // Ordem de severidade para exibição
        $severityOrder = ['high' => 'ALTA', 'medium' => 'MÉDIA', 'low' => 'BAIXA', 'none' => 'NENHUMA'];
        
        foreach ($severityOrder as $severity => $label) {
            if ($restrictionsBySeverity->has($severity)) {
                $description .= "\n\nSeveridade " . $label . ":";
                foreach ($restrictionsBySeverity[$severity] as $restriction) {
                    $description .= "\n- " . $restriction->reason;
                    if ($restriction->expires_at) {
                        $description .= " (Expira em: " . $restriction->expires_at->format('d/m/Y') . ")";
                    }
                }
            }
        }

        $description .= "\n\nOperador: " . Auth::user()->name . " - " . Auth::user()->email;
        $description .= "\nOBS: Ocorrência gerada automaticamente pelo sistema de monitoramento de visitantes.";
        
        return $description;
    }
} 