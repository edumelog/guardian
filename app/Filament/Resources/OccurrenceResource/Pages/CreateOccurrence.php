<?php

namespace App\Filament\Resources\OccurrenceResource\Pages;

use App\Filament\Resources\OccurrenceResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOccurrence extends CreateRecord
{
    protected static string $resource = OccurrenceResource::class;
    
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Ocorrência registrada com sucesso';
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Garante que a data/hora da ocorrência seja sempre a atual
        $data['occurrence_datetime'] = now();
        
        return $data;
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('info')
                ->label('Informação')
                ->icon('heroicon-o-information-circle')
                ->color('gray')
                ->action(function () {
                    Notification::make()
                        ->title('Registro de Ocorrências')
                        ->body('As ocorrências são sempre registradas com a data e hora atual do sistema.')
                        ->info()
                        ->send();
                }),
        ];
    }
}
