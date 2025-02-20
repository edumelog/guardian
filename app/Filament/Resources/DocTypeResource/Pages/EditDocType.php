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
                ->before(function ($record) {
                    if ($record->visitors()->count() > 0) {
                        return false;
                    }
                })
                ->failureNotification(
                    notification: fn ($record) => 
                        "Não é possível excluir o tipo de documento \"{$record->type}\" pois existem visitantes associados a ele."
                ),
        ];
    }
} 