<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class WebcamCapture extends Field
{
    protected string $view = 'filament.forms.components.webcam-capture';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrateStateUsing(function ($state) {
            // Se o estado já for um nome de arquivo, simplesmente retorna
            if (is_string($state) && !str_starts_with($state, 'data:image')) {
                return $state;
            }
            
            // Se o estado for uma string base64, converte para arquivo
            if (is_string($state) && str_starts_with($state, 'data:image')) {
                $docNumber = $this->getLivewire()->data['doc'] ?? null;
                $docTypeId = $this->getLivewire()->data['doc_type_id'] ?? null;

                if (!$docNumber || !$docTypeId) return $state;

                // Obtém o tipo de documento
                $docType = \App\Models\DocType::find($docTypeId);
                if (!$docType) return $state;

                // Remove caracteres especiais do número do documento
                $safeDocNumber = preg_replace('/[^a-zA-Z0-9]/', '', $docNumber);
                
                // Cria um nome de arquivo padronizado sem timestamp para garantir que sempre sobrescreve
                // a foto anterior do mesmo visitante em vez de criar múltiplos arquivos
                $filename = 'photo_' . strtolower($docType->type) . '_' . $safeDocNumber . '.jpg';
                
                // Remove foto anterior se existir e estiver associada ao registro atual
                $record = $this->getRecord();
                if ($record && isset($record->photo) && !empty($record->photo)) {
                    $oldPhotoPath = 'visitors-photos/' . $record->photo;
                    if (Storage::disk('private')->exists($oldPhotoPath)) {
                        \Illuminate\Support\Facades\Log::info("WebcamCapture: Removendo foto anterior: {$record->photo}");
                        Storage::disk('private')->delete($oldPhotoPath);
                    }
                }
                
                // Converte base64 para arquivo
                $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $state));
                
                // Salva o arquivo no disco private
                Storage::disk('private')->put('visitors-photos/' . $filename, $image);
                \Illuminate\Support\Facades\Log::info("WebcamCapture: Salvando nova foto como {$filename}");
                
                return $filename;
            }

            return $state;
        });

        $this->afterStateHydrated(function ($state) {
            // Se o estado for um nome de arquivo, mantém assim
            // O componente blade vai lidar com a exibição
            return $state;
        });
    }

    public static function make(string $name): static
    {
        return parent::make($name);
    }

    public function getStorageDirectory(): string
    {
        return 'visitors-photos';
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