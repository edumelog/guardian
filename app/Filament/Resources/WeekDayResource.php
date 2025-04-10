<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeekDayResource\Pages;
use App\Filament\Resources\WeekDayResource\RelationManagers;
use App\Models\WeekDay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;

/**
 * Resource para gerenciamento de representações dos dias da semana
 * 
 * Este resource permite cadastrar imagens e/ou textos para representar
 * os dias da semana, que serão usados em substituição de marcadores 
 * em templates (tpl-weekday-img e tpl-weekday-txt)
 */
class WeekDayResource extends Resource
{
    protected static ?string $model = WeekDay::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationLabel = 'Dias da Semana';
    
    protected static ?string $modelLabel = 'Dia da Semana';
    
    protected static ?string $pluralModelLabel = 'Dias da Semana';
    
    protected static ?string $navigationGroup = 'Configurações';
    
    protected static ?int $navigationSort = 80;

    public static function form(Form $form): Form
    {
        // Verificamos se estamos em um formulário de edição
        $isEditForm = $form->getOperation() === 'edit';
        
        return $form
            ->schema([
                Section::make('Informações do Dia da Semana')
                    ->schema([
                        // Em modo de edição, o campo day_number será somente leitura
                        $isEditForm
                            ? TextInput::make('day_number')
                                ->label('Dia da Semana')
                                ->formatStateUsing(fn (int $state): string => WeekDay::WEEK_DAYS[$state] ?? 'Desconhecido')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(1)
                            : Select::make('day_number')
                                ->label('Dia da Semana')
                                ->options(WeekDay::WEEK_DAYS)
                                ->required()
                                ->unique()
                                ->columnSpan(1),
                        
                        Toggle::make('is_active')
                            ->label('Ativo')
                            ->default(true)
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                
                Section::make('Representações do Dia')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Imagem do Dia')
                            ->image()
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('512')
                            ->imageResizeTargetHeight('512')
                            ->disk('public')
                            ->directory('week-days')
                            ->columnSpan(1)
                            ->helperText('Upload de uma imagem (PNG ou JPG) para representar o dia da semana. A imagem será redimensionada para 512x512px.'),
                            
                        TextInput::make('text_value')
                            ->label('Texto do Dia')
                            ->maxLength(255)
                            ->placeholder('Ex: SEGUNDA')
                            ->helperText('Digite um texto (será convertido para maiúsculo) para representar o dia da semana.')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_number')
                    ->label('Nº')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => ((string) $state)),
                    
                TextColumn::make('day_name')
                    ->label('Dia da Semana')
                    ->getStateUsing(fn (WeekDay $record): string => $record->day_name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        // Buscar pelo nome do dia na constante WEEK_DAYS
                        $dayNumbers = collect(WeekDay::WEEK_DAYS)
                            ->filter(fn ($name) => str_contains(strtolower($name), strtolower($search)))
                            ->keys();
                            
                        return $query->whereIn('day_number', $dayNumbers);
                    }),
                    
                ImageColumn::make('image')
                    ->label('Imagem')
                    ->disk('public')
                    ->circular(false)
                    ->defaultImageUrl(fn () => asset('images/placeholder.png')),
                    
                TextColumn::make('text_value')
                    ->label('Texto')
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->searchable(),
                    
                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
                    
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('day_number', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Ativo',
                        '0' => 'Inativo',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->bulkActions([]);
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
            'index' => Pages\ListWeekDays::route('/'),
            'create' => Pages\CreateWeekDay::route('/create'),
            'edit' => Pages\EditWeekDay::route('/{record}/edit'),
        ];
    }
}
