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
                            ->disabled(fn (Get $get): bool => $this->showAllFields),
                            
                        TextInput::make('doc')
                            ->label('Número do Documento')
                            ->required()
                            ->maxLength(255)
                            ->numeric()
                            ->inputMode('numeric')
                            ->step(1)
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
        return $this->showAllFields 
            ? parent::getFormActions()
            : [];
    }

    protected function searchVisitor(): void
    {
        if (!$this->data['doc'] || !$this->data['doc_type_id']) {
            return;
        }

        $visitor = \App\Models\Visitor::where('doc', $this->data['doc'])
            ->where('doc_type_id', $this->data['doc_type_id'])
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

        $this->form->fill([
            'doc' => $visitor->doc,
            'doc_type_id' => $visitor->doc_type_id,
            'name' => $visitor->name,
            'photo' => $visitor->photo,
            'doc_photo_front' => $visitor->doc_photo_front,
            'doc_photo_back' => $visitor->doc_photo_back,
            'other' => $visitor->other,
            'destination_id' => $visitor->destination_id,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Garante que o doc e doc_type_id estejam presentes nos dados do formulário
        if (!isset($data['doc'])) {
            $data['doc'] = $this->data['doc'];
        }
        if (!isset($data['doc_type_id'])) {
            $data['doc_type_id'] = $this->data['doc_type_id'];
        }
        return $data;
    }
}
