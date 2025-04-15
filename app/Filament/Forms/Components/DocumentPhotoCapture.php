<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DocumentPhotoCapture extends Field
{
    protected string $view = 'filament.forms.components.document-photo-capture';
    protected string $side = 'front';

    public function side(string $side): static
    {
        $this->side = $side;
        Log::info("DocumentPhotoCapture: side definido como {$side} para o campo {$this->getName()}");
        return $this;
    }

    public function getSide(): string
    {
        Log::info("DocumentPhotoCapture: getSide retornando {$this->side} para o campo {$this->getName()}");
        return $this->side;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrateStateUsing(function ($state) {
            if (empty($state)) return null;
            
            // Se o estado já for um nome de arquivo, verifica se o formato é correto
            if (is_string($state) && !str_starts_with($state, 'data:image')) {
                // Verifica se o nome do arquivo contém o lado correto
                if (!str_contains($state, "_{$this->side}.")) {
                    Log::warning("DocumentPhotoCapture: Nome do arquivo inconsistente com o lado", [
                        'field' => $this->getName(),
                        'side' => $this->side,
                        'filename' => $state
                    ]);
                    
                    // Extrai as partes do nome do arquivo
                    $parts = explode('_', pathinfo($state, PATHINFO_FILENAME));
                    if (count($parts) >= 3) {
                        // Reconstrói o nome do arquivo com o lado correto
                        $newFilename = $parts[0] . '_' . $parts[1] . '_' . $parts[2] . '_' . $this->side . '.jpg';
                        
                        Log::info("DocumentPhotoCapture: Corrigindo nome do arquivo para {$newFilename}");
                        
                        // Verifica se o arquivo existe com o novo nome
                        if (!Storage::disk('private')->exists('visitors-photos/' . $newFilename)) {
                            // Se não existir, copia o arquivo atual com o novo nome
                            if (Storage::disk('private')->exists('visitors-photos/' . $state)) {
                                $fileContent = Storage::disk('private')->get('visitors-photos/' . $state);
                                Storage::disk('private')->put('visitors-photos/' . $newFilename, $fileContent);
                                Log::info("DocumentPhotoCapture: Arquivo copiado para {$newFilename}");
                            }
                        }
                        
                        return $newFilename;
                    }
                }
                
                return $state;
            }

            if (str_starts_with($state, 'data:image')) {
                $docNumber = $this->getLivewire()->data['doc'] ?? null;
                $docTypeId = $this->getLivewire()->data['doc_type_id'] ?? null;

                if (!$docNumber || !$docTypeId) return $state;

                // Obtém o tipo de documento
                $docType = \App\Models\DocType::find($docTypeId);
                if (!$docType) return $state;

                // Remove caracteres especiais do número do documento
                $safeDocNumber = preg_replace('/[^a-zA-Z0-9]/', '', $docNumber);
                
                // Adiciona um timestamp ao nome do arquivo para garantir que é único
                $timestamp = date('YmdHis');
                
                // Cria o nome do arquivo: doc_tipo_numero_lado_timestamp.jpg
                $filename = 'doc_' . strtolower($docType->type) . '_' . $safeDocNumber . '_' . $this->side . '_' . $timestamp . '.jpg';
                
                // Converte base64 para arquivo
                $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $state));
                
                // Salva o arquivo no disco private
                Storage::disk('private')->put('visitors-photos/' . $filename, $image);
                
                Log::info("DocumentPhotoCapture: Salvando nova foto do documento {$this->side} como {$filename}");
                
                return $filename;
            }

            return $state;
        });

        $this->afterStateHydrated(function ($state) {
            // Verifica se o estado é um nome de arquivo e se corresponde ao lado correto
            if (is_string($state) && !empty($state) && !str_starts_with($state, 'data:image')) {
                Log::info("DocumentPhotoCapture: Estado hidratado", [
                    'field' => $this->getName(),
                    'side' => $this->side,
                    'state' => $state
                ]);
                
                // Verifica se o nome do arquivo contém o lado correto
                if (!str_contains($state, "_{$this->side}.")) {
                    Log::warning("DocumentPhotoCapture: Nome do arquivo hidratado inconsistente com o lado", [
                        'field' => $this->getName(),
                        'side' => $this->side,
                        'filename' => $state
                    ]);
                }
            }
        });
    }

    public static function make(string $name): static
    {
        $instance = parent::make($name);
        
        // Define o lado com base no nome do campo, se não for explicitamente definido
        if (str_contains($name, 'front')) {
            $instance->side('front');
        } elseif (str_contains($name, 'back')) {
            $instance->side('back');
        }
        
        \Illuminate\Support\Facades\Log::info("DocumentPhotoCapture::make: Criando componente {$name} com lado {$instance->getSide()}");
        
        return $instance;
    }

    /**
     * Retorna o disco de armazenamento para as fotos
     * 
     * @return string
     */
    public function getStorageDisk(): string
    {
        return 'private';
    }

    public function isDisabled(): bool
    {
        // Verifica se o campo está desabilitado
        if (parent::isDisabled()) {
            return true;
        }

        // Verifica se o visitante tem data de saída
        $record = $this->getRecord();
        if ($record) {
            $lastLog = $record->visitorLogs()->latest('in_date')->first();
            return $lastLog && $lastLog->out_date !== null;
        }

        return false;
    }
} 