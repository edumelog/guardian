<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DestinationResource\Pages;
use App\Models\Destination;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

class DestinationResource extends Resource
{
    protected static ?string $model = Destination::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    
    protected static ?string $navigationGroup = 'Controle de Acesso';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Destino';
    
    protected static ?string $pluralModelLabel = 'Destinos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informações do Destino')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('address')
                            ->label('Endereço')
                            ->maxLength(255),
                            
                        TextInput::make('phone')
                            ->label('Telefone')
                            ->maxLength(255)
                            ->tel(),
                            
                        TextInput::make('max_visitors')
                            ->label('Máximo de Visitantes')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                            
                        Select::make('parent_id')
                            ->label('Destino Pai')
                            ->relationship(
                                'parent',
                                'name',
                                function (Builder $query, $record) {
                                    if ($record) {
                                        $childrenIds = $record->getAllChildrenIds();
                                        $query->whereNotIn('id', [...$childrenIds, $record->id]);
                                    }
                                    return $query;
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('Selecione o destino pai (opcional)'),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Destino Pai')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('address')
                    ->label('Endereço')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('max_visitors')
                    ->label('Máximo de Visitantes')
                    ->numeric()
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
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading(fn(): string => 'Deletar Destino')
                    ->modalDescription(function (Destination $record): string {
                        $childrenCount = $record->children()->count();
                        if ($childrenCount > 0) {
                            return "O destino \"{$record->name}\" possui {$childrenCount} subdestino(s) associado(s). Ao deletar este destino, todos os subdestinos também serão removidos. Deseja continuar?";
                        }
                        return "Tem certeza que deseja deletar o destino \"{$record->name}\"?";
                    })
                    ->modalSubmitActionLabel('Sim, deletar')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Deletar Destinos')
                        ->modalDescription(function ($action): string {
                            $destinationsWithChildren = [];
                            foreach ($action->getRecords() as $record) {
                                if ($record->children()->count() > 0) {
                                    $destinationsWithChildren[] = $record->name;
                                }
                            }
                            
                            if (!empty($destinationsWithChildren)) {
                                $destinationsList = implode('", "', $destinationsWithChildren);
                                return "ATENÇÃO: Os seguintes destinos possuem subdestinos associados:\n\n\"{$destinationsList}\"\n\nAo deletar estes destinos, todos os seus subdestinos também serão removidos. Deseja continuar?";
                            }
                            
                            return 'Tem certeza que deseja deletar os destinos selecionados?';
                        })
                        ->modalSubmitActionLabel('Sim, deletar tudo')
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
            'index' => Pages\ListDestinations::route('/'),
            'create' => Pages\CreateDestination::route('/create'),
            'edit' => Pages\EditDestination::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            // Registra os widgets aqui
        ];
    }    
} 