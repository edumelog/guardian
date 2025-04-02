<?php

namespace App\Filament\Resources\OccurrenceResource\Pages;

use Filament\Actions;
use Filament\Support\Enums\MaxWidth;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\OccurrenceResource;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Form;

class EditOccurrence extends EditRecord
{
    protected static string $resource = OccurrenceResource::class;
    // Max width
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('info')
                ->label('Informação')
                ->icon('heroicon-o-information-circle')
                ->color('gray')
                ->action(function () {
                    Notification::make()
                        ->title('Edição de Ocorrência')
                        ->body('Você está editando uma ocorrência. A data e hora original será preservada. Apenas ocorrências do dia atual podem ser editadas.')
                        ->info()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Garante que a data/hora mostrada seja a original
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Ocorrência atualizada com sucesso';
    }
    
    // Verifica se o registro pode ser editado (apenas no mesmo dia)
    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();
        
        // Usando a política centralizada
        if (!$this->getResource()::canEdit($this->record)) {
            Notification::make()
                ->title('Acesso negado')
                ->body('Apenas ocorrências do dia atual podem ser editadas.')
                ->danger()
                ->send();
                
            redirect($this->getResource()::getUrl('view', ['record' => $this->record]))->send();
            exit;
        }
    }
}
