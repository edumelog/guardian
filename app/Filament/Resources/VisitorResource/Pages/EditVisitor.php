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
        $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
        $hasGivenExit = $lastLog && $lastLog->out_date !== null;

        // Se já deu saída, mostra apenas os botões de cancelar e excluir
        if ($hasGivenExit) {
            return [
                Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->color('gray')
                    ->url($this->getResource()::getUrl('index')),

                Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->action(function () {
                        $hasActiveVisit = $this->record->visitorLogs()
                            ->whereNull('out_date')
                            ->exists();

                        if ($hasActiveVisit) {
                            Notification::make()
                                ->warning()
                                ->title('Exclusão não permitida')
                                ->body('Não é possível excluir um visitante com visita em andamento.')
                                ->send();
                            return;
                        }

                        $this->record->delete();
                        
                        $this->redirect($this->getResource()::getUrl('index'));
                    }),
            ];
        }

        // Se não deu saída, mostra todos os botões
        return [
            Actions\Action::make('reprint')
                ->label('Reimprimir Credencial')
                ->color('warning')
                ->icon('heroicon-o-printer')
                ->action(function () {
                    // TODO: Implementar a lógica de impressão da credencial
                    Notification::make()
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
                    
                    if (!$lastLog || $lastLog->out_date) {
                        Notification::make()
                            ->warning()
                            ->title('Sem visita em andamento')
                            ->body('Este visitante não possui uma visita em andamento.')
                            ->send();
                        return;
                    }

                    $lastLog->update(['out_date' => now()]);
                    
                    Notification::make()
                        ->success()
                        ->title('Saída registrada com sucesso!')
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                })
                ->requiresConfirmation()
                ->modalHeading('Registrar Saída')
                ->modalDescription('Tem certeza que deseja registrar a saída deste visitante?')
                ->modalSubmitActionLabel('Sim, registrar saída'),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),

            Actions\DeleteAction::make()
                ->label('Excluir')
                ->action(function () {
                    $hasActiveVisit = $this->record->visitorLogs()
                        ->whereNull('out_date')
                        ->exists();

                    if ($hasActiveVisit) {
                        Notification::make()
                            ->warning()
                            ->title('Exclusão não permitida')
                            ->body('Não é possível excluir um visitante com visita em andamento.')
                            ->send();
                        return;
                    }

                    $this->record->delete();
                    
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
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
