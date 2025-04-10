<?php

namespace App\Filament\Resources\VisitorResource\Pages;

use App\Filament\Resources\VisitorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Auth;

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
        $lastLog = $this->record->visitorLogs()->latest('in_date')->first();
        $hasGivenExit = $lastLog && $lastLog->out_date !== null;
        $hasActiveVisit = $lastLog && $lastLog->out_date === null;
        
        $actions = [];
        
        // Adiciona o botão de preview apenas se o visitante tiver uma visita em andamento
        if ($hasActiveVisit) {
            $actions[] = Actions\Action::make('previewCredential')
                ->label('Imprimir Credencial')
                ->color('info')
                ->icon('heroicon-o-printer')
                ->action(function () {
                    $this->printVisitorCredential();
                });
        }
        
        // Adiciona o botão de registrar entrada apenas se o visitante já tiver dado saída
        if ($hasGivenExit) {
            $actions[] = Actions\Action::make('register_entry')
                ->label('Registrar Nova Entrada')
                ->icon('heroicon-o-arrow-left-circle')
                ->color('success')
                ->url(function () {
                    // Obtém os dados do visitante
                    $visitor = $this->record;
                    
                    // Constrói a URL para a página de criação com os parâmetros
                    return $this->getResource()::getUrl('create', [
                        'doc' => $visitor->doc,
                        'doc_type_id' => $visitor->doc_type_id
                    ]);
                });
        }
        
        return $actions;
    }

    protected function printVisitorCredential()
    {
        // Registra no log para debug
        \Illuminate\Support\Facades\Log::info('Método printVisitorCredential chamado', [
            'visitor_id' => $this->record->id,
            'visitor_name' => $this->record->name,
        ]);

        // Carrega o visitante com seus relacionamentos
        $visitor = $this->record->load(['docType', 'destination', 'visitorLogs' => function($query) {
            $query->latest('in_date')->first();
        }]);
        
        $lastLog = $visitor->visitorLogs()->latest('in_date')->first();
        
        // Prepara a URL da foto
        $photoUrl = null;
        if ($visitor->photo) {
            // Verifica se a foto existe
            $photoPath = "visitors-photos/{$visitor->photo}";
            if (\Illuminate\Support\Facades\Storage::disk('private')->exists($photoPath)) {
                $photoUrl = route('visitor.photo', ['filename' => $visitor->photo]);
                \Illuminate\Support\Facades\Log::info('URL da foto gerada', [
                    'visitor_id' => $visitor->id,
                    'photo_filename' => $visitor->photo,
                    'photo_path' => $photoPath,
                    'photo_url' => $photoUrl,
                ]);
            } else {
                \Illuminate\Support\Facades\Log::warning('Foto do visitante não encontrada', [
                    'visitor_id' => $visitor->id,
                    'photo_filename' => $visitor->photo,
                    'photo_path' => $photoPath,
                ]);
            }
        } else {
            \Illuminate\Support\Facades\Log::info('Visitante sem foto', [
                'visitor_id' => $visitor->id,
            ]);
        }

        // Prepara os dados do visitante
        $visitorData = [
            'id' => $visitor->id,
            'name' => $visitor->name,
            'doc' => $visitor->doc,
            'docType' => $visitor->docType?->type,
            'destination' => $visitor->destination?->name,
            'destinationAddress' => $visitor->destination?->address,
            'destinationPhone' => $visitor->destination?->phone,
            'destinationAlias' => $visitor->destination?->getFirstAvailableAlias(),
            'photo' => $photoUrl,
            'inDate' => $lastLog?->in_date,
            'outDate' => $lastLog?->out_date,
            'visitLogId' => $lastLog?->id,
            'other' => $visitor->other,
            // Dados adicionais
            'docTypeName' => $visitor->docType->type,
            'docTypeId' => $visitor->docType->id,
            'destinationId' => $visitor->destination->id,
            'destinationName' => $visitor->destination->name,
            'destinationAddress' => $visitor->destination->address,
            'createdAt' => $visitor->created_at->format('d/m/Y H:i'),
            'updatedAt' => $visitor->updated_at->format('d/m/Y H:i'),
        ];

        // Log para debug dos dados do visitante
        \Illuminate\Support\Facades\Log::info('Dados do visitante preparados para impressão:', $visitorData);

        // Adiciona um script inline para carregar o script dinamicamente e então executar a função
        $this->js(<<<JS
            console.log('Iniciando impressão de credencial');
            
            // Função para carregar o script dinamicamente
            function loadScript(url, callback) {
                console.log('Carregando script:', url);
                const script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = url;
                script.onload = function() {
                    console.log('Script carregado com sucesso');
                    callback();
                };
                script.onerror = function() {
                    console.error('Erro ao carregar script de impressão');
                };
                document.head.appendChild(script);
            }
            
            // Dados do visitante
            const visitorData = {$this->encodeVisitorData($visitorData)};
            console.log('Dados do visitante preparados para impressão');
            
            // Verifica se o script já está carregado
            if (typeof window.printVisitorCredential === 'function') {
                console.log('Script já carregado, iniciando impressão');
                window.printVisitorCredential(visitorData);
            } else {
                console.log('Script não carregado, carregando dinamicamente');
                // Carrega o script e então executa a função
                loadScript('/js/visitor-credential-print.js?v=' + new Date().getTime(), function() {
                    if (typeof window.printVisitorCredential === 'function') {
                        console.log('Iniciando impressão');
                        window.printVisitorCredential(visitorData);
                    } else {
                        console.error('Função de impressão não encontrada');
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
            ->title('Impressão de Credencial')
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
        $hasActiveVisit = $lastLog && $lastLog->out_date === null;

        // Se já deu saída, mostra apenas os botões de cancelar e excluir
        if ($hasGivenExit) {
            $actions = [];
            
            // Não exibe o botão de preview para visitantes sem visita em andamento
            
            $actions[] = Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url($this->getResource()::getUrl('index'));
                
            $actions[] = Actions\DeleteAction::make()
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
                });
                
            return $actions;
        }

        // Se não deu saída, mostra todos os botões
        $actions = [];
        
        // Adiciona o botão de preview apenas se o visitante tiver uma visita em andamento
        if ($hasActiveVisit) {
            $actions[] = Actions\Action::make('preview')
                ->label('Imprimir Credencial')
                ->color('info')
                ->icon('heroicon-o-printer')
                ->action(function () {
                    $this->printVisitorCredential();
                });
        }

        $actions[] = Actions\Action::make('register_exit')
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
                
                // Registra ocorrência se necessário
                $occurrenceService = new \App\Services\OccurrenceService();
                $occurrenceService->registerExitOccurrence($this->record, $lastLog);
                
                Notification::make()
                    ->success()
                    ->title('Saída registrada com sucesso!')
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));
            })
            ->requiresConfirmation()
            ->modalHeading('Registrar Saída')
            ->modalDescription('Tem certeza que deseja registrar a saída deste visitante?')
            ->modalSubmitActionLabel('Sim, registrar saída');

        $actions[] = Actions\Action::make('cancel')
            ->label('Cancelar')
            ->color('gray')
            ->url($this->getResource()::getUrl('index'));

        $actions[] = Actions\DeleteAction::make()
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
            });
            
        return $actions;
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
        
        // Log para depuração
        \Illuminate\Support\Facades\Log::info('EditVisitor: Dados do formulário antes de salvar', [
            'formData' => $this->form->getRawState(),
            'record' => $this->record->toArray()
        ]);
        
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
