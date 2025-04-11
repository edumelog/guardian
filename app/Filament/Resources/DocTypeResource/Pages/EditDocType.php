<?php

namespace App\Filament\Resources\DocTypeResource\Pages;

use App\Filament\Resources\DocTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocType extends EditRecord
{
    protected static string $resource = DocTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Deletar Tipo de Documento')
                ->modalDescription(fn ($record): string => 
                    "Tem certeza que deseja deletar o tipo de documento \"{$record->type}\"?"
                )
                ->modalSubmitActionLabel('Sim, deletar')
                ->visible(fn ($record): bool => $record->visitors()->count() === 0)
                ->before(function (Actions\DeleteAction $action, $record) {
                    if ($visitorsCount = $record->visitors()->count()) {
                        // Impede a exclusão se houver visitantes associados
                        $action->cancel();
                        
                        // Notificação detalhada com o número de visitantes
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Exclusão não permitida')
                            ->body("Não é possível excluir o tipo de documento \"{$record->type}\" pois existem {$visitorsCount} visitante(s) associado(s) a ele.")
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
} 