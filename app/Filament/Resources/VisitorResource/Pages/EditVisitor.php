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
        return [
            Actions\Action::make('reprintCredential')
                ->label('Reimprimir Credencial')
                ->color('warning')
                ->icon('heroicon-o-printer')
                ->action(function () {
                    $this->printVisitorCredential();
                }),
        ];
    }

    protected function printVisitorCredential()
    {
        // Registra no log para debug
        \Illuminate\Support\Facades\Log::info('Método printVisitorCredential chamado', [
            'visitor_id' => $this->record->id,
            'visitor_name' => $this->record->name,
        ]);

        // Prepara os dados do visitante
        $visitorData = [
            'id' => $this->record->id,
            'name' => $this->record->name,
            'doc' => $this->record->doc,
            'docType' => $this->record->docType->type,
            'photo' => $this->record->photo ? "/storage/visitors-photos/{$this->record->photo}" : null,
            'destination' => $this->record->destination->name,
            'destinationAddress' => $this->record->destination->address,
            'inDate' => $this->record->visitorLogs()->latest('in_date')->first()?->in_date->format('d/m/Y H:i'),
            'other' => $this->record->other,
        ];

        // Adiciona um script inline para carregar o script dinamicamente e então executar a função
        $this->js(<<<JS
            console.log('Iniciando impressão de credencial via JS inline');
            
            // Função para carregar o script dinamicamente
            function loadScript(url, callback) {
                console.log('Carregando script:', url);
                const script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = url;
                script.onload = function() {
                    console.log('Script carregado com sucesso:', url);
                    callback();
                };
                script.onerror = function() {
                    console.error('Erro ao carregar script:', url);
                };
                document.head.appendChild(script);
            }
            
            // Dados do visitante
            const visitorData = {$this->encodeVisitorData($visitorData)};
            console.log('Dados do visitante:', visitorData);
            
            // Verifica se o script já está carregado
            if (typeof window.printVisitorCredential === 'function') {
                console.log('Script já carregado, chamando função diretamente');
                window.printVisitorCredential(visitorData);
            } else {
                console.log('Script não carregado, carregando dinamicamente');
                // Carrega o script e então executa a função
                loadScript('/js/visitor-credential-print.js?v=' + new Date().getTime(), function() {
                    console.log('Script carregado, verificando função');
                    // Verifica novamente se a função está disponível
                    if (typeof window.printVisitorCredential === 'function') {
                        console.log('Função encontrada após carregar script, chamando');
                        window.printVisitorCredential(visitorData);
                    } else {
                        console.error('Função ainda não encontrada após carregar script');
                        // Tenta disparar o evento diretamente
                        const event = new CustomEvent('print-visitor-credential', {
                            detail: {
                                visitor: visitorData
                            }
                        });
                        document.dispatchEvent(event);
                    }
                });
            }
        JS);

        Notification::make()
            ->success()
            ->title('Credencial enviada para impressão')
            ->body('A credencial foi enviada para impressão.')
            ->send();
    }
    
    // Método auxiliar para codificar os dados do visitante em JSON
    protected function encodeVisitorData($data)
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
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
                    $this->printVisitorCredential();
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
