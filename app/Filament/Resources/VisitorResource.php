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
        return $form
            ->schema([
                Section::make('Informações do Visitante')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\Select::make('doc_type_id')
                            ->label('Tipo de Documento')
                            ->relationship('docType', 'type')
                            ->required()
                            ->default(function () {
                                return \App\Models\DocType::where('is_default', true)->first()?->id;
                            })
                            ->live(),
                            
                        Forms\Components\TextInput::make('doc')
                            ->label('Número do Documento')
                            ->required()
                            ->maxLength(255)
                            ->numeric()
                            ->inputMode('numeric')
                            ->step(1)
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
                            ->validationMessages([
                                'required' => 'A foto é obrigatória para o cadastro do visitante.'
                            ]),
                            
                        Forms\Components\Select::make('destination_id')
                            ->label('Destino')
                            ->required()
                            ->searchable()
                            ->live()
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
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make()
                    ->schema([
                        Forms\Components\View::make('filament.forms.components.destination-hierarchy-view')
                            ->columnSpanFull(),
                    ])
                    ->hiddenOn('edit'),
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
                    ->getStateUsing(fn (Visitor $record): ?string => 
                        $record->photo ? "visitors-photos/{$record->photo}" : null
                    )
                    ->disk('public'),
                    
                Tables\Columns\TextColumn::make('destination.name')
                    ->label('Destino')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
