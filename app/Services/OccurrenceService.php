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
        
        $description = "Registro de saída de visitante com Restrições de Acesso:

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

    /**
     * Registra uma ocorrência quando um visitante com restrições é autorizado
     * 
     * @param Visitor|null $visitor O visitante (ou null se estiver no processo de criação)
     * @param array $visitorData Dados do visitante (usado se $visitor for null)
     * @param array|Collection $restrictions As restrições que foram autorizadas
     * @param string|null $destination O destino da visita
     * @return Occurrence|null A ocorrência criada ou null se nenhuma foi criada
     */
    public function registerAuthorizationOccurrence($visitor, $visitorData, $restrictions, $destination = null)
    {
        // Se não há restrições, não registra ocorrência
        if (empty($restrictions) || (is_countable($restrictions) && count($restrictions) === 0)) {
            Log::info('[Ocorrência Automática - Autorização] Nenhuma restrição para registrar');
            return null;
        }
        
        // Converter para collection se for array
        if (is_array($restrictions)) {
            $restrictions = collect($restrictions);
        }
        
        // Filtra apenas restrições com auto_occurrence habilitado
        $restrictionsWithAutoOccurrence = $restrictions->filter(function ($restriction) {
            return $restriction->auto_occurrence ?? false;
        });
        
        // Se não houver restrições com auto_occurrence, não registra
        if ($restrictionsWithAutoOccurrence->isEmpty()) {
            Log::info('[Ocorrência Automática - Autorização] Nenhuma restrição com auto_occurrence habilitado');
            return null;
        }
        
        // Obtém a restrição mais crítica para determinar a severidade
        $highestSeverity = $this->getHighestSeverityLevel($restrictionsWithAutoOccurrence);
        
        // Prepara a descrição da ocorrência
        $description = $this->prepareAuthorizationOccurrenceDescription(
            $visitor, 
            $visitorData, 
            $restrictionsWithAutoOccurrence, 
            $destination
        );
        
        // Cria a ocorrência
        $occurrence = Occurrence::create([
            'description' => $description,
            'severity' => $this->mapSeverityLevel($highestSeverity),
            'occurrence_datetime' => now(),
            'created_by' => Auth::id(),
            'updated_by' => null,
            'is_editable' => false,
        ]);
        
        // Vincula o visitante à ocorrência se ele já existir
        if ($visitor && $visitor->id) {
            $occurrence->visitors()->attach($visitor->id);
        }
        
        // Vincula o destino à ocorrência se fornecido
        if ($destination && is_object($destination) && method_exists($destination, 'getKey')) {
            $occurrence->destinations()->attach($destination->getKey());
        } elseif (is_numeric($destination)) {
            $occurrence->destinations()->attach($destination);
        }
        
        Log::info('[Ocorrência Automática - Autorização] Ocorrência registrada com sucesso', [
            'occurrence_id' => $occurrence->id,
            'visitor_id' => $visitor?->id ?? 'Em processo de criação',
            'total_restrictions' => $restrictionsWithAutoOccurrence->count(),
            'restrictions' => $restrictionsWithAutoOccurrence->map(fn($r) => [
                'id' => $r->id ?? null,
                'reason' => $r->reason,
                'severity' => $r->severity_level,
                'type' => isset($r->is_predictive) && $r->is_predictive ? 'Preditiva' : 'Comum'
            ])->toArray()
        ]);
        
        return $occurrence;
    }
    
    /**
     * Prepara a descrição da ocorrência de autorização
     * 
     * @param Visitor|null $visitor
     * @param array $visitorData
     * @param \Illuminate\Support\Collection $restrictions
     * @param mixed $destination
     * @return string
     */
    private function prepareAuthorizationOccurrenceDescription($visitor, $visitorData, $restrictions, $destination): string
    {
        // Log para depuração dos dados recebidos
        \Illuminate\Support\Facades\Log::info('prepareAuthorizationOccurrenceDescription', [
            'visitor' => $visitor ? 'Visitor ID: ' . $visitor->id : 'null',
            'visitorData' => $visitorData,
            'destination' => $destination,
            'restrictions' => count($restrictions)
        ]);

        // Tenta obter informações da primeira restrição comum se for disponível
        $firstCommonRestriction = $restrictions->first(function($restriction) {
            return isset($restriction->is_predictive) && !$restriction->is_predictive;
        });
        
        // Se temos uma restrição comum, podemos tentar obter o visitante dela
        $visitorFromRestriction = null;
        if ($firstCommonRestriction && isset($firstCommonRestriction->visitor_id)) {
            $visitorFromRestriction = \App\Models\Visitor::find($firstCommonRestriction->visitor_id);
            \Illuminate\Support\Facades\Log::info('Visitor from restriction', [
                'found' => $visitorFromRestriction ? 'Sim' : 'Não',
                'visitor_id' => $firstCommonRestriction->visitor_id
            ]);
        }

        // Obtém dados do visitante (priorizando na ordem: objeto visitor > visitorFromRestriction > visitorData)
        $visitorName = $visitor ? $visitor->name : ($visitorFromRestriction ? $visitorFromRestriction->name : ($visitorData['name'] ?? 'Não informado'));
        $visitorDoc = $visitor ? $visitor->doc : ($visitorFromRestriction ? $visitorFromRestriction->doc : ($visitorData['doc'] ?? 'Não informado'));
        
        $docTypeName = 'Desconhecido';
        if ($visitor && $visitor->docType) {
            $docTypeName = $visitor->docType->type;
        } elseif ($visitorFromRestriction && $visitorFromRestriction->docType) {
            $docTypeName = $visitorFromRestriction->docType->type;
        } elseif (!empty($visitorData['doc_type_id'])) {
            $docType = \App\Models\DocType::find($visitorData['doc_type_id']);
            $docTypeName = $docType ? $docType->type : 'Desconhecido';
        }
        
        $visitorPhone = $visitor ? $visitor->phone : ($visitorFromRestriction ? $visitorFromRestriction->phone : ($visitorData['phone'] ?? 'N/A'));
        
        // Obtém dados do destino
        $destinationName = 'Não informado';
        if ($destination && is_object($destination) && method_exists($destination, 'getAttribute')) {
            $destinationName = $destination->getAttribute('name') ?? 'Não informado';
        } elseif (!empty($visitorData['destination_id'])) {
            $destinationObj = \App\Models\Destination::find($visitorData['destination_id']);
            $destinationName = $destinationObj ? $destinationObj->name : 'Não informado';
        }
        
        $description = "Autorização de entrada de visitante com Restrições de Acesso:

Dados do visitante:
Nome: " . $visitorName . "
Documento: " . $visitorDoc . " (" . $docTypeName . ")
Telefone: " . $visitorPhone . "
Destino: " . $destinationName . "

Restrições autorizadas (" . $restrictions->count() . "):";

        // Agrupa as restrições por severidade
        $restrictionsBySeverity = $restrictions->groupBy('severity_level');
        
        // Ordem de severidade para exibição
        $severityOrder = ['high' => 'ALTA', 'medium' => 'MÉDIA', 'low' => 'BAIXA', 'none' => 'NENHUMA'];
        
        foreach ($severityOrder as $severity => $label) {
            if ($restrictionsBySeverity->has($severity)) {
                $description .= "\n\nSeveridade " . $label . ":";
                foreach ($restrictionsBySeverity[$severity] as $restriction) {
                    $description .= "\n- " . $restriction->reason;
                    if (isset($restriction->expires_at) && $restriction->expires_at) {
                        $expiryDate = is_string($restriction->expires_at) 
                            ? $restriction->expires_at 
                            : $restriction->expires_at->format('d/m/Y');
                        $description .= " (Expira em: " . $expiryDate . ")";
                    }
                }
            }
        }

        // Utiliza os dados do autorizador se disponíveis, caso contrário usa o usuário logado
        $authorizerName = $visitorData['authorizer_name'] ?? \Illuminate\Support\Facades\Auth::user()->name;
        $authorizerEmail = $visitorData['authorizer_email'] ?? \Illuminate\Support\Facades\Auth::user()->email;
        
        $description .= "\n\nAutorizado por: " . $authorizerName . " - " . $authorizerEmail;
        $description .= "\nOBS: Ocorrência gerada automaticamente pelo sistema de monitoramento de visitantes.";
        
        return $description;
    }
} 