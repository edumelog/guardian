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
            Actions\Action::make('previewCredential')
                ->label('Preview da Credencial')
                ->color('info')
                ->icon('heroicon-o-eye')
                ->action(function () {
                    $this->previewVisitorCredential();
                }),
        ];
    }

    protected function previewVisitorCredential()
    {
        // Registra no log para debug
        \Illuminate\Support\Facades\Log::info('Método previewVisitorCredential chamado', [
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
            console.log('Iniciando preview de credencial via JS inline');
            
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
            console.log('URL da foto do visitante:', visitorData.photo);
            
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
            ->title('Preview da Credencial')
            ->body('O preview da credencial foi aberto.')
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
            Actions\Action::make('preview')
                ->label('Preview da Credencial')
                ->color('info')
                ->icon('heroicon-o-eye')
                ->action(function () {
                    $this->previewVisitorCredential();
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
