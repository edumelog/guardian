<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Storage;

class DocumentPhotoCapture extends Field
{
    protected string $view = 'filament.forms.components.document-photo-capture';
    protected string $side = 'front';

    public function side(string $side): static
    {
        $this->side = $side;
        return $this;
    }

    public function getSide(): string
    {
        return $this->side;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrateStateUsing(function ($state) {
            if (empty($state) || !is_string($state)) return null;

            if (str_starts_with($state, 'data:image')) {
                $docNumber = $this->getLivewire()->data['doc'] ?? null;
                $docTypeId = $this->getLivewire()->data['doc_type_id'] ?? null;

                if (!$docNumber || !$docTypeId) return $state;

                // Obtém o tipo de documento
                $docType = \App\Models\DocType::find($docTypeId);
                if (!$docType) return $state;

                // Remove caracteres especiais do número do documento
                $safeDocNumber = preg_replace('/[^a-zA-Z0-9]/', '', $docNumber);
                
                // Cria o nome do arquivo: doc_tipo_numero_lado.jpg
                $filename = 'doc_' . strtolower($docType->type) . '_' . $safeDocNumber . '_' . $this->side . '.jpg';
                
                // Converte base64 para arquivo
                $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $state));
                
                // Salva o arquivo
                Storage::disk('public')->put('visitors-photos/' . $filename, $image);
                
                return $filename;
            }

            return $state;
        });
    }

    public static function make(string $name): static
    {
        return parent::make($name);
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