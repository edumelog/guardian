<?php

namespace App\Filament\Resources\PartialVisitorRestrictionResource\Pages;

use App\Filament\Resources\PartialVisitorRestrictionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class CreatePartialVisitorRestriction extends CreateRecord
{
    protected static string $resource = PartialVisitorRestrictionResource::class;
    
    protected function beforeCreate(): void
    {
        // Verifica se pelo menos um campo de identificação do visitante foi preenchido
        $data = $this->form->getState();
        
        // Contador de campos preenchidos
        $fieldsFilledCount = 0;
        
        // Verifica nome parcial
        if (!empty($data['partial_name'])) {
            $fieldsFilledCount++;
        }
        
        // Verifica documento parcial
        if (!empty($data['partial_doc'])) {
            $fieldsFilledCount++;
        }
        
        // Verifica telefone
        if (!empty($data['phone'])) {
            $fieldsFilledCount++;
        }
        
        // Se nenhum dos campos de identificação foi preenchido, mostra erro
        if ($fieldsFilledCount === 0) {
            Notification::make()
                ->title('Validação')
                ->body('Pelo menos um campo de identificação (Nome, Documento ou Telefone) deve ser preenchido.')
                ->danger()
                ->send();
            
            $this->halt();
        }
    }
    
    protected function afterCreate(): void
    {
        $record = $this->record;
        
        Log::info('PartialVisitorRestriction criada', [
            'id' => $record->id,
            'partial_name' => $record->partial_name,
            'partial_doc' => $record->partial_doc,
            'doc_type_id' => $record->doc_type_id,
            'phone' => $record->phone,
            'severity_level' => $record->severity_level,
            'created_by' => $record->created_by,
        ]);
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
