<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitorResource\Pages;
use App\Filament\Resources\VisitorResource\RelationManagers;
use App\Models\Visitor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use App\Filament\Forms\Components\WebcamCapture;
use App\Filament\Forms\Components\DestinationTreeSelect;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\Placeholder;
use App\Filament\Forms\Components\DocumentPhotoCapture;

class VisitorResource extends Resource
{
    protected static ?string $model = Visitor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    
    protected static ?string $navigationGroup = 'Controle de Acesso';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Visitante';
    
    protected static ?string $pluralModelLabel = 'Visitantes';

    public static function form(Form $form): Form
    {
        $hasActiveVisit = false;
        if ($form->getRecord()) {
            $hasActiveVisit = $form->getRecord()
                ->visitorLogs()
                ->whereNull('out_date')
                ->exists();
        }

        return $form
            ->schema([
                Section::make('Informações do Visitante')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->disabled($hasActiveVisit),
                            
                        Forms\Components\Select::make('doc_type_id')
                            ->label('Tipo de Documento')
                            ->relationship('docType', 'type')
                            ->required()
                            ->default(function () {
                                return \App\Models\DocType::where('is_default', true)->first()?->id;
                            })
                            ->live()
                            ->disabled($hasActiveVisit),
                            
                        Forms\Components\TextInput::make('doc')
                            ->label('Número do Documento')
                            ->required()
                            ->maxLength(255)
                            ->numeric()
                            ->inputMode('numeric')
                            ->step(1)
                            ->disabled($hasActiveVisit)
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('search')
                                    ->icon('heroicon-m-magnifying-glass')
                                    ->tooltip('Buscar visitante por documento')
                                    ->action(function ($state, $component) {
                                        // Se não houver número de documento ou tipo de documento, retorna
                                        if (!$state || !$component->getContainer()->getParentComponent()->getState()['doc_type_id']) {
                                            return;
                                        }

                                        // Busca o visitante pelo documento e tipo
                                        $visitor = \App\Models\Visitor::where('doc', $state)
                                            ->where('doc_type_id', $component->getContainer()->getParentComponent()->getState()['doc_type_id'])
                                            ->first();
                                            
                                        
                                        if (!$visitor) {
                                            \Filament\Notifications\Notification::make()
                                                ->warning()
                                                ->title('Visitante não encontrado')
                                                ->body('Nenhum visitante encontrado com este documento.')
                                                ->send();
                                            return;
                                        }

                                        // Preenche os campos com os dados encontrados
                                        $livewire = $component->getLivewire();
                                        $livewire->data['name'] = $visitor->name;
                                        $livewire->data['photo'] = $visitor->photo;
                                        $livewire->data['doc_photo_front'] = $visitor->doc_photo_front;
                                        $livewire->data['doc_photo_back'] = $visitor->doc_photo_back;
                                        $livewire->data['other'] = $visitor->other;

                                        // Log para debug
                                        $photoData = [
                                            'photo' => $visitor->photo ? '/storage/visitors-photos/' . $visitor->photo : null,
                                            'doc_photo_front' => $visitor->doc_photo_front ? '/storage/visitors-photos/' . $visitor->doc_photo_front : null,
                                            'doc_photo_back' => $visitor->doc_photo_back ? '/storage/visitors-photos/' . $visitor->doc_photo_back : null,
                                        ];
                                        
                                        // Dispara eventos para atualizar os previews das fotos
                                        $component->getLivewire()->dispatch('photo-found', photoData: $photoData);

                                        // Log para debug
                                        $component->getLivewire()->js("
                                            console.log('Dados do visitante encontrado:', " . json_encode([
                                                'nome' => $visitor->name,
                                                'photo' => $visitor->photo,
                                                'doc_photo_front' => $visitor->doc_photo_front,
                                                'doc_photo_back' => $visitor->doc_photo_back,
                                                'photoData' => $photoData
                                            ]) . ");
                                        ");

                                        \Filament\Notifications\Notification::make()
                                            ->success()
                                            ->title('Visitante encontrado')
                                            ->body('Os dados do visitante foram preenchidos automaticamente.')
                                            ->send();
                                    })
                            )
                            ->extraInputAttributes(['step' => '1', 'class' => '[appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none'])
                            ->unique(
                                table: 'visitors',
                                column: 'doc',
                                ignorable: fn ($record) => $record,
                                modifyRuleUsing: function (Forms\Components\TextInput $component, \Illuminate\Validation\Rules\Unique $rule) {
                                    return $rule->where('doc_type_id', $component->getContainer()->getParentComponent()->getState()['doc_type_id']);
                                }
                            )
                            ->validationMessages([
                                'unique' => 'Já existe um visitante cadastrado com este número de documento para o tipo selecionado.',
                                'numeric' => 'O número do documento deve conter apenas números.'
                            ]),
                            
                        WebcamCapture::make('photo')
                            ->label('Foto')
                            ->required()
                            ->disabled($hasActiveVisit),

                        DocumentPhotoCapture::make('doc_photo_front')
                            ->label('Documento - Frente')
                            ->required()
                            ->side('front')
                            ->disabled($hasActiveVisit),

                        DocumentPhotoCapture::make('doc_photo_back')
                            ->label('Documento - Verso')
                            ->required()
                            ->side('back')
                            ->disabled($hasActiveVisit),
                            
                        Forms\Components\Select::make('destination_id')
                            ->label('Destino')
                            ->required()
                            ->searchable()
                            ->live()
                            ->disabled($hasActiveVisit)
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

                        Forms\Components\Placeholder::make('destination_phone')
                            ->label('Telefone do Destino')
                            ->content(function ($get) {
                                $destinationId = $get('destination_id');
                                if (!$destinationId) return '-';
                                
                                $destination = \App\Models\Destination::find($destinationId);
                                return $destination?->phone ?: 'Não cadastrado';
                            })
                            ->columnSpanFull(),
                            
                        Forms\Components\Textarea::make('other')
                            ->label('Informações Adicionais')
                            ->maxLength(255)
                            ->disabled($hasActiveVisit)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('current_entry')
                            ->label('Data de Entrada')
                            ->content(now()->format('d/m/Y H:i')),

                        Forms\Components\Placeholder::make('last_visit')
                            ->label('Última Visita')
                            ->content(function ($record) {
                                if (!$record) return 'Primeira visita';
                                
                                $lastLog = $record->visitorLogs()
                                    ->orderBy('in_date', 'desc')
                                    ->first();

                                if (!$lastLog) return 'Primeira visita';

                                return sprintf(
                                    'Local: %s - Data: %s',
                                    $lastLog->destination->name,
                                    $lastLog->in_date->format('d/m/Y H:i')
                                );
                            }),
                    ])->columns(2),

                Section::make()
                    ->schema([
                        Forms\Components\View::make('filament.forms.components.destination-hierarchy-view')
                            ->columnSpanFull(),
                    ])
                    ->hiddenOn('edit'),

                Forms\Components\Section::make('Histórico de Visitas')
                    ->description('Clique para expandir/recolher o histórico')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('visit_history')
                            ->content(function ($record) {
                                if (!$record) return 'Nenhuma visita registrada';

                                $logs = $record->visitorLogs()
                                    ->with('destination')
                                    ->orderBy('in_date', 'desc')
                                    ->get();

                                if ($logs->isEmpty()) return 'Nenhuma visita registrada';

                                $html = '<div class="space-y-4">';
                                foreach ($logs as $log) {
                                    $html .= '<div class="p-4 bg-gray-50 rounded-lg space-y-2">';
                                    $html .= '<div class="font-medium text-gray-900">Local: ' . e($log->destination->name) . '</div>';
                                    $html .= '<div class="text-sm text-gray-600">Entrada: ' . $log->in_date->format('d/m/Y H:i') . '</div>';
                                    $html .= '<div class="text-sm text-gray-600">Saída: ' . ($log->out_date ? $log->out_date->format('d/m/Y H:i') : 'Não registrada') . '</div>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('docType.type')
                    ->label('Tipo de Documento')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('doc')
                    ->label('Documento')
                    ->searchable(),
                    
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->width(40)
                    ->height(40)
                    ->getStateUsing(fn (Visitor $record): ?string => 
                        $record->photo ? "visitors-photos/{$record->photo}" : null
                    )
                    ->disk('public'),

                // Tables\Columns\ImageColumn::make('doc_photo_front')
                //     ->label('Doc. Frente')
                //     ->getStateUsing(fn (Visitor $record): ?string => 
                //         $record->doc_photo_front ? "visitors-photos/{$record->doc_photo_front}" : null
                //     )
                //     ->disk('public'),

                // Tables\Columns\ImageColumn::make('doc_photo_back')
                //     ->label('Doc. Verso')
                //     ->getStateUsing(fn (Visitor $record): ?string => 
                //         $record->doc_photo_back ? "visitors-photos/{$record->doc_photo_back}" : null
                //     )
                //     ->disk('public'),
                    
                Tables\Columns\TextColumn::make('destination.name')
                    ->label('Destino')
                    ->sortable(),

                Tables\Columns\TextColumn::make('visitorLogs.in_date')
                    ->label('Entrada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->getStateUsing(function (Visitor $record) {
                        return $record->visitorLogs()->latest('in_date')->first()?->in_date;
                    }),

                Tables\Columns\TextColumn::make('visitorLogs.out_date')
                    ->label('Saída')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->getStateUsing(function (Visitor $record) {
                        return $record->visitorLogs()->latest('in_date')->first()?->out_date;
                    }),
                    
                // Tables\Columns\TextColumn::make('created_at')
                //     ->label('Criado em')
                //     ->dateTime('d/m/Y H:i')
                //     ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('register_exit')
                    ->label('Registrar Saída')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->action(function (Visitor $record) {
                        $lastLog = $record->visitorLogs()->latest('in_date')->first();
                        if ($lastLog && !$lastLog->out_date) {
                            $lastLog->update(['out_date' => now()]);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Registrar Saída')
                    ->modalDescription('Tem certeza que deseja registrar a saída deste visitante?')
                    ->modalSubmitActionLabel('Sim, registrar saída')
                    ->visible(function (Visitor $record): bool {
                        $lastLog = $record->visitorLogs()->latest('in_date')->first();
                        return $lastLog && !$lastLog->out_date;
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(function (Visitor $record): bool {
                        return !$record->visitorLogs()->whereNull('out_date')->exists();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(function (Visitor $record): bool {
                        return !$record->visitorLogs()->whereNull('out_date')->exists();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisitors::route('/'),
            'create' => Pages\CreateVisitor::route('/create'),
            'edit' => Pages\EditVisitor::route('/{record}/edit'),
        ];
    }
}
