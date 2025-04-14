<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, $record) {
                    // Verifica se o usuário está tentando excluir a própria conta
                    if ($record->id === \Illuminate\Support\Facades\Auth::id()) {
                        
                        \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('Operação não permitida')
                        ->body("Não é possível excluir sua própria conta de usuário.")
                        ->persistent()
                        ->send();
                        
                        $action->cancel();
                        return;
                    }
                    
                    // Verifica ocorrências relacionadas
                    if ($record->hasRelatedOccurrences()) {
                        
                        // Notifica o usuário com uma mensagem clara
                        \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('Exclusão não permitida')
                        ->body("Não é possível excluir o usuário '{$record->name}' pois existem ocorrências que foram criadas ou modificadas por ele.")
                        ->persistent()
                        ->send();
                        // Cancela a ação de exclusão
                        $action->cancel();
                    }
                }),
        ];
    }
}
