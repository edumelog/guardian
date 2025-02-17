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
                
                // Cria o nome do arquivo: photo_tipo_numero.jpg
                $filename = 'photo_' . strtolower($docType->type) . '_' . $safeDocNumber . '.jpg';
                
                // Converte base64 para arquivo
                $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $state));
                
                // Salva o arquivo
                Storage::disk('public')->put('visitors-photos/' . $filename, $image);
                
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
} 