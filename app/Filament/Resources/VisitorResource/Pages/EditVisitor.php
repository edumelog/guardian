<?php

namespace App\Filament\Resources\VisitorResource\Pages;

use App\Filament\Resources\VisitorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Forms\Form;

class EditVisitor extends EditRecord
{
    protected static string $resource = VisitorResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
        
        if ($lastLog && $lastLog->out_date !== null) {
            // Mostra uma notificação explicando por que não pode editar
            Notification::make()
                ->warning()
                ->title('Visitante já saiu')
                ->body('Não é possível editar os dados de um visitante que já saiu.')
                ->persistent()
                ->send();
        }
    }

    public function form(Form $form): Form
    {
        $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
        $isDisabled = $lastLog && $lastLog->out_date !== null;

        return parent::form($form)
            ->disabled($isDisabled);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        $hasActiveVisit = $this->record->visitorLogs()
            ->whereNull('out_date')
            ->exists();

        // Se houver visita em andamento, mostra o botão de reimprimir e registrar saída
        if ($hasActiveVisit) {
            return [
                Actions\Action::make('reprint')
                    ->label('Reimprimir Credencial')
                    ->color('warning')
                    ->icon('heroicon-o-printer')
                    ->action(function () {
                        // TODO: Implementar a lógica de impressão da credencial
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Impressão de Credencial')
                            ->body('Funcionalidade em desenvolvimento.')
                            ->send();
                    }),
                Actions\Action::make('register_exit')
                    ->label('Registrar Saída')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->action(function () {
                        $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
                        if ($lastLog && !$lastLog->out_date) {
                            $lastLog->update(['out_date' => now()]);
                            
                            // Notifica o usuário
                            Notification::make()
                                ->success()
                                ->title('Saída registrada com sucesso!')
                                ->send();

                            // Redireciona para a lista de visitantes
                            $this->redirect($this->getResource()::getUrl('index'));
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Registrar Saída')
                    ->modalDescription('Tem certeza que deseja registrar a saída deste visitante?')
                    ->modalSubmitActionLabel('Sim, registrar saída'),
            ];
        }

        // Caso contrário, mostra apenas os botões de cancelar e excluir
        return [
            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
            Actions\DeleteAction::make()
                ->label('Excluir'),
        ];
    }

    protected bool $saving = false;

    protected function getSavedNotification(): ?Notification
    {
        // Se estiver salvando pela ação de impressão, não mostra notificação
        if ($this->saving) {
            return null;
        }

        // Caso contrário, mostra a notificação padrão
        return parent::getSavedNotification();
    }

    public function beforeSave(): void
    {
        $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
        
        // Se o visitante já saiu, não permite salvar alterações
        if ($lastLog && $lastLog->out_date !== null) {
            Notification::make()
                ->danger()
                ->title('Operação não permitida')
                ->body('Não é possível editar os dados de um visitante que já saiu.')
                ->send();
                
            $this->halt();
        }
    }
}
