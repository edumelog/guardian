<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OccurrenceResource\Pages;
use App\Filament\Resources\OccurrenceResource\RelationManagers;
use App\Models\Occurrence;
use App\Models\Visitor;
use App\Models\Destination;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Support\Colors\Color;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class OccurrenceResource extends Resource
{
    protected static ?string $model = Occurrence::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    
    protected static ?string $navigationGroup = 'Análise de Segurança';
    
    protected static ?string $navigationLabel = 'Registro de Ocorrências';
    
    protected static ?string $pluralModelLabel = 'Ocorrências';
    
    protected static ?string $modelLabel = 'Ocorrência';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informações da Ocorrência')
                    ->description('Registre os detalhes da ocorrência de segurança')
                    ->schema([
                        DateTimePicker::make('occurrence_datetime')
                            ->label('Data e Hora da Ocorrência')
                            ->required()
                            ->default(now())
                            ->seconds(false)
                            ->timezone('America/Sao_Paulo')
                            ->columnSpan(2)
                            ->disabled()
                            ->helperText('A ocorrência é registrada automaticamente com a data e hora atual.'),
                            
                        Select::make('severity')
                            ->label('Severidade')
                            ->options([
                                'gray' => 'Nenhuma (Cinza)',
                                'green' => 'Baixa (Verde)',
                                'amber' => 'Média (Amarelo)',
                                'red' => 'Alta (Vermelho)',
                            ])
                            ->required()
                            ->default('gray')
                            ->disabled(fn (Get $get, $record) => $record && !$record->is_editable)
                            ->helperText(fn (Get $get, $record) => 
                                $record && !$record->is_editable 
                                    ? 'A severidade não pode ser alterada nesta ocorrência.'
                                    : null
                            )
                            ->reactive(),
                            
                        Textarea::make('description')
                            ->label('Descrição da Ocorrência')
                            ->required()
                            ->disabled(fn (Get $get, $record) => $record && !$record->is_editable)
                            ->columnSpan(2)
                            ->rows(5),
                    ])
                    ->columns(2),
                    
                Section::make('Vínculos (Opcional)')
                    ->description('Vincule visitantes e/ou destinos relacionados a esta ocorrência')
                    ->disabled(fn (Get $get, $record) => $record && !$record->is_editable)
                    ->schema([
                        Select::make('visitors')
                            ->label('Visitantes Relacionados')
                            ->multiple()
                            ->relationship('visitors', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome')
                                    ->required(),
                                Forms\Components\TextInput::make('doc')
                                    ->label('Documento')
                                    ->required(),
                            ]),
                            
                        Select::make('destinations')
                            ->label('Destinos Relacionados')
                            ->multiple()
                            ->relationship('destinations', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurrence_datetime', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                    
                TextColumn::make('occurrence_datetime')
                    ->label('Data e Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(50)
                    ->tooltip(fn (Occurrence $record): string => $record->description)
                    ->searchable(),
                    
                Tables\Columns\BadgeColumn::make('severity')
                    ->label('Severidade')
                    ->formatStateUsing(fn (string $state): string => Occurrence::SEVERITY_LEVELS[$state] ?? $state)
                    ->colors([
                        'success' => 'green',
                        'warning' => 'amber',
                        'danger' => 'red',
                    ]),
                    
                TextColumn::make('creator.name')
                    ->label('Registrado por')
                    ->sortable(),
                    
                TextColumn::make('visitors.name')
                    ->label('Visitantes')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList(),
                    
                TextColumn::make('destinations.name')
                    ->label('Destinos')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList(),
                    
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('today')
                    ->label('Apenas hoje')
                    ->default()
                    ->query(function (Builder $query) {
                        $today = Carbon::today('America/Sao_Paulo');
                        return $query->whereDate('occurrence_datetime', $today);
                    }),
                    
                Tables\Filters\SelectFilter::make('severity')
                    ->label('Severidade')
                    ->options(Occurrence::SEVERITY_LEVELS),
                    
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('De'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('occurrence_datetime', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('occurrence_datetime', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(function (Occurrence $record): bool {
                        return static::canEdit($record);
                    })
                    ->tooltip('Editar ocorrência'),
                    
                Tables\Actions\ViewAction::make()
                    ->label('Visualizar')
                    ->visible(function (Occurrence $record): bool {
                        // Mostrar apenas para ocorrências de dias anteriores
                        return !static::canEdit($record);
                    })
                    ->tooltip('Visualizar ocorrência'),
                    
                Tables\Actions\DeleteAction::make()
                    ->visible(function (Occurrence $record): bool {
                        return static::canDelete($record);
                    })
                    ->tooltip('Excluir ocorrência'),
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
            'index' => Pages\ListOccurrences::route('/'),
            'create' => Pages\CreateOccurrence::route('/create'),
            'edit' => Pages\EditOccurrence::route('/{record}/edit'),
            'view' => Pages\ViewOccurrence::route('/{record}'),
        ];
    }
    
    public static function getEloquentQuery(): Builder
    {
        // Por padrão, mostrar apenas as ocorrências do dia atual
        $query = parent::getEloquentQuery();
        
        // Filtro default pode ser removido pelo usuário na interface se necessário
        return $query;
    }

    public static function canEdit(Model $record): bool
    {
        // Apenas permite editar ocorrências do dia atual
        $occurrenceDate = Carbon::parse($record->occurrence_datetime)->startOfDay();
        $today = Carbon::today();
        return $occurrenceDate->equalTo($today);
    }
    
    public static function canDelete(Model $record): bool
    {
        // Apenas permite deletar ocorrências do dia atual
        return static::canEdit($record);
    }
}
