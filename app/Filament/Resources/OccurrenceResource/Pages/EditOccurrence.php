<?php

namespace App\Filament\Resources\OccurrenceResource\Pages;

use Filament\Actions;
use Filament\Forms\Form;
use Illuminate\Support\Carbon;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\OccurrenceResource;

class EditOccurrence extends EditRecord
{
    protected static string $resource = OccurrenceResource::class;
    // Max width
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Salvar alterações')
                ->submit('save')
                ->keyBindings(['mod+s'])
                ->color('primary')
                ->disabled(fn() => !$this->record->is_editable),
                
            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }

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
