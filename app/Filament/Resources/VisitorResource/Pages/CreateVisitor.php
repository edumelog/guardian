<?php

namespace App\Filament\Resources\VisitorResource\Pages;

use App\Filament\Resources\VisitorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use App\Filament\Forms\Components\WebcamCapture;
use App\Filament\Forms\Components\DocumentPhotoCapture;
use Filament\Forms\Get;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\View;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class CreateVisitor extends CreateRecord
{
    protected static string $resource = VisitorResource::class;

    public bool $showAllFields = false;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informações do Visitante')
                    ->schema([
                        Select::make('doc_type_id')
                            ->label('Tipo de Documento')
                            ->relationship('docType', 'type')
                            ->required()
                            ->default(function () {
                                return \App\Models\DocType::where('is_default', true)->first()?->id;
                            })
                            ->live()
                            ->dehydrated(true)
                            ->disabled(fn (Get $get): bool => $this->showAllFields),
                            
                        TextInput::make('doc')
                            ->label('Número do Documento')
                            ->required()
                            ->maxLength(255)
                            ->numeric()
                            ->inputMode('numeric')
                            ->step(1)
                            ->dehydrated(true)
                            ->disabled(fn (Get $get): bool => $this->showAllFields)
                            ->suffixAction(
                                Action::make('search')
                                    ->icon('heroicon-m-magnifying-glass')
                                    ->tooltip('Buscar visitante por documento')
                                    ->action(fn () => $this->searchVisitor())
                            ),

                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $this->showAllFields),

                        WebcamCapture::make('photo')
                            ->label('Foto')
                            ->required()
                            ->visible(fn (Get $get): bool => $this->showAllFields),

                        DocumentPhotoCapture::make('doc_photo_front')
                            ->label('Foto do Documento (Frente)')
                            ->required()
                            ->visible(fn (Get $get): bool => $this->showAllFields),

                        DocumentPhotoCapture::make('doc_photo_back')
                            ->label('Foto do Documento (Verso)')
                            ->required()
                            ->visible(fn (Get $get): bool => $this->showAllFields),

                        Select::make('destination_id')
                            ->label('Destino')
                            ->required()
                            ->searchable()
                            ->live()
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->getSearchResultsUsing(function (string $search) {
                                return \App\Models\Destination::where('is_active', true)
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('address', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($destination) => [
                                        $destination->id => $destination->address 
                                            ? "{$destination->name} - {$destination->address}"
                                            : $destination->name
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => 
                                \App\Models\Destination::where('is_active', true)
                                    ->find($value)?->address 
                                        ? \App\Models\Destination::find($value)->name . ' - ' . \App\Models\Destination::find($value)->address
                                        : \App\Models\Destination::find($value)?->name
                            )
                            ->placeholder('Digite o nome ou endereço do destino')
                            ->columnSpanFull(),

                        Placeholder::make('destination_phone')
                            ->label('Telefone do Destino')
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->content(function ($get) {
                                $destinationId = $get('destination_id');
                                if (!$destinationId) return '-';
                                
                                $destination = \App\Models\Destination::find($destinationId);
                                if (!$destination || !$destination->is_active) {
                                    return '-';
                                }
                                return $destination->phone ?: 'Não cadastrado';
                            })
                            ->columnSpanFull(),

                        Placeholder::make('visitors_count')
                            ->label('Visitantes presentes no destino:')
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->content(function ($get) {
                                $destinationId = $get('destination_id');
                                if (!$destinationId) return '-';
                                
                                $destination = \App\Models\Destination::find($destinationId);
                                if (!$destination || !$destination->is_active) {
                                    return '-';
                                }

                                $currentCount = $destination->getCurrentVisitorsCount();
                                $maxVisitors = $destination->max_visitors;

                                // Se não tem limite, mostra em preto sem destaque
                                if ($maxVisitors <= 0) {
                                    return new \Illuminate\Support\HtmlString(
                                        "<span class='text-gray-900'>{$currentCount}</span>"
                                    );
                                }

                                // Calcula a porcentagem de ocupação
                                $occupancyRate = ($currentCount / $maxVisitors) * 100;

                                // Define a cor e estilo baseado na ocupação
                                if ($currentCount >= $maxVisitors) {
                                    // Vermelho quando atingir o limite
                                    $style = 'text-red-600 dark:text-red-400';
                                    
                                    \Filament\Notifications\Notification::make()
                                        ->warning()
                                        ->title('Limite de visitantes atingido')
                                        ->body("O destino {$destination->name} atingiu o limite de {$maxVisitors} visitantes.")
                                        ->persistent()
                                        ->send();
                                } elseif ($occupancyRate >= 50 && $occupancyRate < 80) {
                                    // Laranja entre 50% e 80%
                                    $style = 'text-orange-500 dark:text-orange-400';
                                } else {
                                    // Verde abaixo de 50%
                                    $style = 'text-emerald-600 dark:text-emerald-400';
                                }

                                return new \Illuminate\Support\HtmlString(
                                    "<span class='{$style}'>{$currentCount}/{$maxVisitors}</span>"
                                );
                            })
                            ->columnSpanFull(),
                            
                        Textarea::make('other')
                            ->label('Informações Adicionais')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->columnSpanFull(),

                        Placeholder::make('current_entry')
                            ->label('Data de Entrada')
                            ->visible(fn (Get $get): bool => $this->showAllFields)
                            ->content(now()->format('d/m/Y H:i')),
                    ])->columns(2),

                Section::make()
                    ->schema([
                        View::make('filament.forms.components.destination-hierarchy-view')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (): bool => $this->showAllFields),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        // Se não estiver mostrando todos os campos, não mostra nenhuma ação
        if (!$this->showAllFields) {
            return [];
        }

        // Sempre mostra o botão de criar com impressão e o botão cancelar
        return [
            $this->getCreateFormAction()
                ->label('Imprimir Credencial e Salvar')
                ->color('success')
                ->icon('heroicon-o-printer')
                ->action(function () {
                    // Verifica se há visita em andamento
                    $formData = $this->form->getState();
                    
                    $visitor = \App\Models\Visitor::where('doc', $formData['doc'] ?? null)
                        ->where('doc_type_id', $formData['doc_type_id'] ?? null)
                        ->first();

                    if ($visitor) {
                        $lastVisit = $visitor->visitorLogs()
                            ->latest('in_date')
                            ->first();

                        if ($lastVisit && $lastVisit->out_date === null) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Visita em Andamento')
                                ->body("Este visitante já possui uma visita em andamento.")
                                ->persistent()
                                ->send();
                            return;
                        }
                    }

                    $this->create();
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('gray')
                ->url(url()->previous()),
        ];
    }

    protected function searchVisitor(): void
    {
        $formData = $this->form->getState();
        
        if (!isset($formData['doc']) || !isset($formData['doc_type_id'])) {
            return;
        }

        $visitor = \App\Models\Visitor::where('doc', $formData['doc'])
            ->where('doc_type_id', $formData['doc_type_id'])
            ->first();
            
        if (!$visitor) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Visitante não encontrado')
                ->body('Nenhum visitante encontrado com este documento.')
                ->send();

            $this->showAllFields = true;
            return;
        }

        // Verifica se há uma visita em andamento
        $lastVisit = $visitor->visitorLogs()
            ->latest('in_date')
            ->first();

        if ($lastVisit && $lastVisit->out_date === null) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Visita em Andamento')
                ->body("Este visitante já possui uma visita em andamento no local: {$lastVisit->destination->name}")
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('Ver Detalhes')
                        ->url(route('filament.dashboard.resources.visitors.edit', $visitor))
                        ->button(),
                ])
                ->send();
            return;
        }

        // Se não tem visita em andamento, preenche os dados
        $this->form->fill([
            'doc' => $visitor->doc,
            'doc_type_id' => $visitor->doc_type_id,
            'name' => $visitor->name,
            'photo' => $visitor->photo,
            'doc_photo_front' => $visitor->doc_photo_front,
            'doc_photo_back' => $visitor->doc_photo_back,
            'other' => $visitor->other,
        ]);

        // Dispara eventos para atualizar os previews das fotos
        $photoData = [
            'photo' => $visitor->photo ? route('visitor.photo', ['filename' => $visitor->photo]) : null,
            'doc_photo_front' => null,
            'doc_photo_back' => null,
        ];
        
        // Verifica se os nomes dos arquivos das fotos dos documentos são consistentes com o lado
        if ($visitor->doc_photo_front) {
            // Verifica se o nome do arquivo contém '_front.'
            if (strpos($visitor->doc_photo_front, '_front.') !== false) {
                $photoData['doc_photo_front'] = route('visitor.photo', ['filename' => $visitor->doc_photo_front]);
            } else {
                // Extrai as partes do nome do arquivo
                $parts = explode('_', pathinfo($visitor->doc_photo_front, PATHINFO_FILENAME));
                if (count($parts) >= 2) {
                    // Reconstrói o nome do arquivo com o lado correto
                    $correctFilename = $parts[0] . '_' . $parts[1] . '_front.jpg';
                    \Illuminate\Support\Facades\Log::warning("CreateVisitor: Nome do arquivo da foto frontal inconsistente", [
                        'original' => $visitor->doc_photo_front,
                        'corrected' => $correctFilename
                    ]);
                    $photoData['doc_photo_front'] = route('visitor.photo', ['filename' => $correctFilename]);
                } else {
                    $photoData['doc_photo_front'] = route('visitor.photo', ['filename' => $visitor->doc_photo_front]);
                }
            }
        }
        
        if ($visitor->doc_photo_back) {
            // Verifica se o nome do arquivo contém '_back.'
            if (strpos($visitor->doc_photo_back, '_back.') !== false) {
                $photoData['doc_photo_back'] = route('visitor.photo', ['filename' => $visitor->doc_photo_back]);
            } else {
                // Extrai as partes do nome do arquivo
                $parts = explode('_', pathinfo($visitor->doc_photo_back, PATHINFO_FILENAME));
                if (count($parts) >= 2) {
                    // Reconstrói o nome do arquivo com o lado correto
                    $correctFilename = $parts[0] . '_' . $parts[1] . '_back.jpg';
                    \Illuminate\Support\Facades\Log::warning("CreateVisitor: Nome do arquivo da foto traseira inconsistente", [
                        'original' => $visitor->doc_photo_back,
                        'corrected' => $correctFilename
                    ]);
                    $photoData['doc_photo_back'] = route('visitor.photo', ['filename' => $correctFilename]);
                } else {
                    $photoData['doc_photo_back'] = route('visitor.photo', ['filename' => $visitor->doc_photo_back]);
                }
            }
        }
        
        // Log para depuração
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Dados das fotos', [
            'photoData' => $photoData
        ]);
        
        $this->dispatch('photo-found', photoData: $photoData);

        $this->showAllFields = true;

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Visitante encontrado')
            ->body('Os dados do visitante foram preenchidos automaticamente.')
            ->send();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Verifica se o visitante já existe
        $formData = $this->form->getRawState();
        
        // Log para depuração
        \Illuminate\Support\Facades\Log::info('CreateVisitor: Dados do formulário', [
            'formData' => $formData,
            'data' => $data
        ]);
        
        $doc = $formData['doc'] ?? null;
        $docTypeId = $formData['doc_type_id'] ?? null;
        $destinationId = $formData['destination_id'] ?? null;

        if (!$doc || !$docTypeId) {
            $this->halt();
            return $data;
        }

        // Verifica se o destino está ativo
        $destination = \App\Models\Destination::find($destinationId);
        if (!$destination || !$destination->is_active) {
            Notification::make()
                ->danger()
                ->title('Destino inválido')
                ->body('O destino selecionado está inativo ou não existe.')
                ->send();
            $this->halt();
            return $data;
        }

        // Verifica o limite de visitantes
        if ($destination->max_visitors > 0) {
            $currentCount = $destination->getCurrentVisitorsCount();
            if ($currentCount >= $destination->max_visitors) {
                Notification::make()
                    ->danger()
                    ->title('Limite de visitantes atingido')
                    ->body("O destino {$destination->name} atingiu o limite de {$destination->max_visitors} visitantes.")
                    ->persistent()
                    ->send();
                $this->halt();
                return $data;
            }
        }

        $visitor = \App\Models\Visitor::where('doc', $doc)
            ->where('doc_type_id', $docTypeId)
            ->first();

        if ($visitor) {
            // Se o visitante existe, cria apenas um novo log de visita
            $visitor->visitorLogs()->create([
                'destination_id' => $data['destination_id'],
                'in_date' => now(),
                'operator_id' => Auth::id(),
            ]);

            // Notifica o usuário
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Nova visita registrada')
                ->body('Uma nova visita foi registrada para o visitante existente.')
                ->send();

            // Redireciona para a página de edição do visitante
            $this->redirect($this->getResource()::getUrl('edit', ['record' => $visitor]));
            
            $this->halt();
        }

        // Se o visitante não existe, inclui os dados do documento
        $data['doc'] = $doc;
        $data['doc_type_id'] = $docTypeId;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Verifica se o destino está ativo
        $formData = $this->form->getRawState();
        $destination = \App\Models\Destination::find($formData['destination_id']);
        
        if (!$destination || !$destination->is_active) {
            Notification::make()
                ->danger()
                ->title('Destino inválido')
                ->body('O destino selecionado está inativo ou não existe.')
                ->send();
            return;
        }

        // Cria o log de visita APENAS se for um novo visitante
        // (visitantes existentes já têm o log criado em mutateFormDataBeforeCreate)
        if (!$this->record->visitorLogs()->where('in_date', now())->exists()) {
            $this->record->visitorLogs()->create([
                'destination_id' => $formData['destination_id'],
                'in_date' => now(),
                'operator_id' => Auth::id(),
            ]);
        }

        // Redireciona para a página de edição
        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
    }
}
