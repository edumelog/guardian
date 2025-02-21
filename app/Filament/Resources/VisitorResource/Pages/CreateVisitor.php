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
                                return \App\Models\Destination::where('name', 'like', "%{$search}%")
                                    ->orWhere('address', 'like', "%{$search}%")
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
                                \App\Models\Destination::find($value)?->address 
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
                                return $destination?->phone ?: 'Não cadastrado';
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

        // Verifica se há visita em andamento
        $formData = $this->form->getState();
        
        $visitor = \App\Models\Visitor::where('doc', $formData['doc'] ?? null)
            ->where('doc_type_id', $formData['doc_type_id'] ?? null)
            ->first();

        $hasActiveVisit = false;
        if ($visitor) {
            $hasActiveVisit = $visitor->visitorLogs()
                ->whereNull('out_date')
                ->exists();
        }

        // Se houver visita em andamento, mostra apenas o botão de reimprimir
        if ($hasActiveVisit) {
            return [
                \Filament\Actions\Action::make('reprint')
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
            ];
        }

        // Caso contrário, mostra o botão padrão de criar
        return [
            $this->getCreateFormAction()
                ->label('Imprimir Credencial e Salvar')
                ->color('success')
                ->icon('heroicon-o-printer'),
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
        $activeVisit = $visitor->visitorLogs()
            ->whereNull('out_date')
            ->latest('in_date')
            ->first();

        if ($activeVisit) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Visita em Andamento')
                ->body("Este visitante já possui uma visita em andamento no local: {$activeVisit->destination->name}")
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

        $this->form->fill([
            'doc' => $visitor->doc,
            'doc_type_id' => $visitor->doc_type_id,
            'name' => $visitor->name,
            'photo' => $visitor->photo,
            'doc_photo_front' => $visitor->doc_photo_front,
            'doc_photo_back' => $visitor->doc_photo_back,
            'other' => $visitor->other,
        ]);

        $this->showAllFields = true;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Verifica se o visitante já existe
        $formData = $this->form->getRawState();
        
        $doc = $formData['doc'] ?? null;
        $docTypeId = $formData['doc_type_id'] ?? null;

        if (!$doc || !$docTypeId) {
            $this->halt();
            return $data;
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
        // Cria o log de visita para o novo visitante
        $formData = $this->form->getRawState();
        
        $this->record->visitorLogs()->create([
            'destination_id' => $formData['destination_id'],
            'in_date' => now(),
            'operator_id' => Auth::id(),
        ]);

        // Redireciona para a página de edição
        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
    }
}
