<?php

namespace App\Filament\Resources\CommonVisitorRestrictionResource\Pages;

use App\Filament\Resources\CommonVisitorRestrictionResource;
use App\Models\CommonVisitorRestriction;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCommonVisitorRestriction extends CreateRecord
{
    protected static string $resource = CommonVisitorRestrictionResource::class;
    
    /**
     * Método que executa antes de criar um registro
     * Verifica se já existe uma restrição ativa para o visitante e se a nova restrição está sendo ativada
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Só verificamos se a nova restrição está sendo definida como ativa
        if (isset($data['active']) && $data['active']) {
            // Verifica se já existe uma restrição ativa para este visitante
            $existingRestriction = CommonVisitorRestriction::where('visitor_id', $data['visitor_id'])
                ->where('active', true)
                ->first();
                
            if ($existingRestriction) {
                // Exibe notificação e interrompe a criação
                Notification::make()
                    ->danger()
                    ->title('Não foi possível criar a restrição como ativa')
                    ->body('Este visitante já possui uma restrição ativa. Desative-a antes de criar uma nova ativa, ou crie uma restrição desativada.')
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('Ver Restrição Existente')
                            ->url(route('filament.dashboard.resources.common-visitor-restrictions.edit', $existingRestriction))
                            ->button(),
                    ])
                    ->send();
                    
                $this->halt();
            }
        }
        
        return $data;
    }
    
    /**
     * Método que configura as ações do formulário
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Criar Restrição'),
            $this->getCancelFormAction(),
        ];
    }
    
    /**
     * Método que executa após salvar o registro
     * Atualiza o campo has_restrictions do visitante
     */
    protected function afterSave(): void
    {
        // Atualiza o campo has_restrictions do visitante
        $record = $this->getRecord();
        if ($record->visitor) {
            $record->visitor->updateHasRestrictions();
        }
        
        parent::afterSave();
    }
}
