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
        return [
            Actions\Action::make('print_badge')
                ->label('Imprimir Credencial e Salvar')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->extraAttributes([
                    'class' => 'order-1',
                ])
                ->disabled(function (): bool {
                    $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
                    return $lastLog && $lastLog->out_date !== null;
                })
                ->tooltip(function (): string {
                    $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
                    return $lastLog && $lastLog->out_date !== null 
                        ? 'Não é possível imprimir a credencial de um visitante que já saiu'
                        : 'Imprimir credencial do visitante';
                })
                ->action(function () {
                    try {
                        // Tenta salvar as alterações
                        $this->saving = true; // Flag para suprimir notificação padrão
                        $this->save();
                        $this->saving = false;

                        // Se chegou aqui, não houve erros de validação
                        Notification::make()
                            ->title('Sucesso!')
                            ->body('Credencial impressa e alterações salvas.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        $this->saving = false;
                        // Se houver qualquer erro, não prossegue com a impressão
                        Notification::make()
                            ->title('Erro ao salvar alterações')
                            ->body('Corrija os erros antes de imprimir a credencial.')
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('register_exit')
                ->label('Registrar Saída')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'order-2',
                ])
                ->action(function () {
                    $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
                    if ($lastLog && !$lastLog->out_date) {
                        $lastLog->update(['out_date' => now()]);
                        $this->refreshFormData(['visit_history']);
                        
                        // Recarrega a página para atualizar o estado dos campos
                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Registrar Saída')
                ->modalDescription('Tem certeza que deseja registrar a saída deste visitante?')
                ->modalSubmitActionLabel('Sim, registrar saída')
                ->visible(function (): bool {
                    $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
                    return $lastLog && !$lastLog->out_date;
                }),
            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->extraAttributes([
                    'class' => 'order-3',
                ])
                ->url($this->getResource()::getUrl('index')),
            Actions\DeleteAction::make()
                ->label('Excluir')
                ->extraAttributes([
                    'class' => 'ms-auto order-last',
                ]),
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
