<?php

namespace App\Services;

use App\Models\PredictiveVisitorRestriction;
use App\Models\Occurrence;
use App\Models\Visitor;
use App\Models\AutomaticOccurrence;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PredictiveRestrictionService
{
    /**
     * Verifica se há restrições preditivas aplicáveis aos dados fornecidos
     * 
     * @param array $visitorData Dados do visitante a serem verificados
     * @return array Array de objetos de restrição encontrados
     */
    public function checkRestrictions(array $visitorData): array
    {
        Log::info('PredictiveRestrictionService: Verificando restrições preditivas', [
            'name' => $visitorData['name'] ?? null,
            'doc' => $visitorData['doc'] ?? null,
            'doc_type_id' => $visitorData['doc_type_id'] ?? null,
        ]);

        // Carrega todas as restrições preditivas ativas
        $restrictions = PredictiveVisitorRestriction::where('active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        if ($restrictions->isEmpty()) {
            Log::info('PredictiveRestrictionService: Nenhuma restrição preditiva ativa encontrada');
            return [];
        }

        $matchedRestrictions = [];
        $visitorName = mb_strtoupper($visitorData['name'] ?? '');
        $visitorDoc = $visitorData['doc'] ?? '';
        $visitorDocTypeId = $visitorData['doc_type_id'] ?? null;
        $destinationId = $visitorData['destination_id'] ?? null;

        foreach ($restrictions as $restriction) {
            $matchesPattern = true;

            // Verificação do padrão de nome
            if (!empty($restriction->name_pattern) && !$this->matchesWildcardPattern($visitorName, $restriction->name_pattern)) {
                $matchesPattern = false;
            }

            // Verificação do tipo de documento
            if (!$restriction->any_document_type && !empty($restriction->document_types)) {
                $documentTypes = $restriction->document_types;
                if (!in_array($visitorDocTypeId, $documentTypes)) {
                    $matchesPattern = false;
                }
            }

            // Verificação do padrão de número de documento
            if (!empty($restriction->document_number_pattern) && !$this->matchesWildcardPattern($visitorDoc, $restriction->document_number_pattern)) {
                $matchesPattern = false;
            }

            // Verificação de destino
            if (!$restriction->any_destination && !empty($restriction->destinations) && $destinationId) {
                $destinations = $restriction->destinations;
                if (!in_array($destinationId, $destinations)) {
                    $matchesPattern = false;
                }
            }

            // Se todos os padrões aplicáveis forem correspondidos
            if ($matchesPattern) {
                Log::info('PredictiveRestrictionService: Restrição preditiva encontrada', [
                    'id' => $restriction->id,
                    'severity' => $restriction->severity_level,
                    'reason' => $restriction->reason
                ]);

                // Cria um objeto com os dados da restrição
                $restrictionObj = (object) [
                    'id' => $restriction->id,
                    'reason' => $restriction->reason,
                    'severity_level' => $restriction->severity_level,
                    'expires_at' => $restriction->expires_at,
                    'restriction_type' => 'Restrição Preditiva',
                    'name_pattern' => $restriction->name_pattern,
                    'document_pattern' => $restriction->document_number_pattern,
                    'auto_occurrence' => $restriction->auto_occurrence,
                ];

                $matchedRestrictions[] = $restrictionObj;
            }
        }

        Log::info('PredictiveRestrictionService: Verificação concluída', [
            'matches_count' => count($matchedRestrictions)
        ]);

        return $matchedRestrictions;
    }

    /**
     * Registra uma ocorrência para uma restrição preditiva
     * 
     * @param PredictiveVisitorRestriction $restriction A restrição preditiva
     * @param array $visitorData Dados do visitante para documentação
     * @return Occurrence|null A ocorrência criada ou null
     * @deprecated Esse método está obsoleto. As ocorrências agora são geradas no CreateVisitor.php
     */
    protected function registerOccurrence(PredictiveVisitorRestriction $restriction, array $visitorData): ?Occurrence
    {
        // Log para indicar que o método está obsoleto
        Log::warning('PredictiveRestrictionService: Método registerOccurrence está obsoleto. As ocorrências agora são geradas no CreateVisitor.php', [
            'restriction_id' => $restriction->id
        ]);
        
        // Verifica se a ocorrência automática está habilitada
        $automaticOccurrence = AutomaticOccurrence::where('key', 'predictive_visitor_restriction')->first();
        
        if (!$automaticOccurrence || !$automaticOccurrence->enabled) {
            Log::info('PredictiveRestrictionService: Ocorrência automática desabilitada', [
                'restriction_id' => $restriction->id
            ]);
            return null;
        }

        try {
            // Prepara os detalhes para a ocorrência
            $visitorName = $visitorData['name'] ?? 'Nome não fornecido';
            $visitorDoc = $visitorData['doc'] ?? 'Documento não fornecido';
            $docTypeId = $visitorData['doc_type_id'] ?? null;
            $destinationId = $visitorData['destination_id'] ?? null;
            
            // Tenta encontrar um visitante existente com esses dados
            $visitor = null;
            if (!empty($visitorDoc) && !empty($docTypeId)) {
                $visitor = Visitor::where('doc', $visitorDoc)
                    ->where('doc_type_id', $docTypeId)
                    ->first();
            }

            // Descrição da ocorrência
            $description = "Restrição Preditiva Detectada\n";
            $description .= "Severidade: {$restriction->severity_level}\n";
            $description .= "Motivo: {$restriction->reason}\n\n";
            $description .= "Dados do Visitante:\n";
            $description .= "Nome: {$visitorName}\n";
            $description .= "Documento: {$visitorDoc}\n";
            
            if ($docTypeId) {
                $docType = \App\Models\DocType::find($docTypeId);
                $description .= "Tipo de Documento: " . ($docType ? $docType->type : "Tipo #{$docTypeId}") . "\n";
            }
            
            if ($destinationId) {
                $destination = \App\Models\Destination::find($destinationId);
                $description .= "Destino: " . ($destination ? $destination->name : "Destino #{$destinationId}") . "\n";
            }
            
            $description .= "\nDetalhes do Padrão:\n";
            if (!empty($restriction->name_pattern)) {
                $description .= "Padrão de Nome: {$restriction->name_pattern}\n";
            }
            if (!empty($restriction->document_number_pattern)) {
                $description .= "Padrão de Documento: {$restriction->document_number_pattern}\n";
            }
            
            $description .= "\nRegistrado por: " . Auth::user()->name . " (" . Auth::user()->email . ")\n";
            $description .= "Data/Hora: " . now()->format('d/m/Y H:i:s');

            // Cria a ocorrência com a severidade mapeada corretamente
            $occurrence = Occurrence::create([
                'description' => $description,
                'severity' => match ($restriction->severity_level) {
                    'none' => 'gray',
                    'low' => 'green',
                    'medium' => 'amber',
                    'high', 'critical' => 'red',
                    default => 'gray',
                },
                'occurrence_datetime' => now(),
                'created_by' => Auth::id(),
                'updated_by' => null,
            ]);
            
            // Vincular o visitante à ocorrência (se já existir)
            if ($visitor) {
                $occurrence->visitors()->attach($visitor->id);
            }
            
            // Vincular o destino à ocorrência (se existir)
            if ($destinationId) {
                $occurrence->destinations()->attach($destinationId);
            }

            Log::info('PredictiveRestrictionService: Ocorrência registrada com sucesso (obsoleto)', [
                'occurrence_id' => $occurrence->id,
                'restriction_id' => $restriction->id
            ]);

            return $occurrence;
        } catch (\Exception $e) {
            Log::error('PredictiveRestrictionService: Erro ao registrar ocorrência', [
                'error' => $e->getMessage(),
                'restriction_id' => $restriction->id
            ]);
            return null;
        }
    }

    /**
     * Verifica se um texto corresponde a um padrão de wildcard
     * 
     * @param string $text O texto a ser verificado
     * @param string $pattern O padrão com wildcards (* e ?)
     * @return bool True se o texto corresponder ao padrão
     */
    protected function matchesWildcardPattern(string $text, string $pattern): bool
    {
        // Se o padrão estiver vazio, não há o que corresponder
        if (empty($pattern)) {
            return true;
        }

        // Normaliza o texto e o padrão para maiúsculas para comparação case-insensitive
        $text = mb_strtoupper($text);
        $pattern = mb_strtoupper($pattern);
        
        Log::info('Verificando padrão wildcard', [
            'texto' => $text,
            'padrão' => $pattern
        ]);

        // Escapa caracteres especiais de regex, mas preserva * e ?
        $pattern = preg_quote($pattern, '/');
        
        // Substitui os wildcards por seus equivalentes em regex
        $pattern = str_replace(
            ['\*', '\?'],
            ['.*', '.'],
            $pattern
        );
        
        // Cria o regex completo com âncoras
        $regex = '/^' . $pattern . '$/u';
        
        Log::info('Regex gerado', [
            'regex' => $regex
        ]);

        // Verifica se o texto corresponde ao regex
        $match = preg_match($regex, $text);
        
        Log::info('Resultado da verificação', [
            'match' => (bool) $match
        ]);
        
        return (bool) $match;
    }
} 