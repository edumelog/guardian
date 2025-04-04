<?php

namespace App\Filament\Resources\CommonVisitorRestrictionResource\Pages;

use App\Filament\Resources\CommonVisitorRestrictionResource;
use App\Models\CommonVisitorRestriction;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCommonVisitorRestriction extends EditRecord
{
    protected static string $resource = CommonVisitorRestrictionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    /**
     * Método que executa antes de salvar as alterações
     * Verifica se está tentando ativar uma restrição quando já existe outra ativa
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        
        // Se estiver tentando ativar a restrição
        if (!$record->active && isset($data['active']) && $data['active']) {
            // Verifica se já existe outra restrição ativa para este visitante
            $existingRestriction = CommonVisitorRestriction::where('visitor_id', $record->visitor_id)
                ->where('active', true)
                ->where('id', '!=', $record->id)
                ->first();
                
            if ($existingRestriction) {
                // Exibe notificação e interrompe a ativação
                Notification::make()
                    ->danger()
                    ->title('Não foi possível ativar a restrição')
                    ->body('Este visitante já possui uma restrição ativa. Desative-a antes de ativar esta.')
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('Ver Restrição Ativa')
                            ->url(route('filament.dashboard.resources.common-visitor-restrictions.edit', $existingRestriction))
                            ->button(),
                    ])
                    ->send();
                    
                // Interrompe completamente o processo de salvamento para não exibir a notificação de "Salvo"
                $this->halt();
            }
        }
        
        return $data;
    }
    
    /**
     * Hook que é executado após o registro ser salvo
     */
    protected function afterSaved(): void
    {
        // Atualiza o campo has_restrictions do visitante
        $record = $this->getRecord();
        if ($record->visitor) {
            $record->visitor->updateHasRestrictions();
        }
    }
}
