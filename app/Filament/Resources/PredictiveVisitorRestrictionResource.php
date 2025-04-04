<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PredictiveVisitorRestrictionResource\Pages;
use App\Filament\Resources\PredictiveVisitorRestrictionResource\RelationManagers;
use App\Models\PredictiveVisitorRestriction;
use App\Models\Destination;
use App\Models\DocType;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PredictiveVisitorRestrictionResource extends Resource
{
    protected static ?string $model = PredictiveVisitorRestriction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Análise de Segurança';

    protected static ?string $navigationLabel = 'Restrição Preditiva';

    protected static ?string $pluralModelLabel = 'Restrições Preditivas';

    protected static ?string $modelLabel = 'Restrição Preditiva';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Padrões de Identificação')
                    ->description('Configure os padrões para detectar potenciais restrições. Utilize * para qualquer sequência de caracteres e ? para um único caractere.')
                    ->schema([
                        TextInput::make('name_pattern')
                            ->label('Padrão de Nome')
                            ->placeholder('Ex: J*Silva ou Jo?é*')
                            ->helperText('Deixe em branco para não filtrar por nome. Utilize * e ? como curingas.')
                            ->maxLength(255),
                            
                        Grid::make(2)
                            ->schema([
                                Toggle::make('any_document_type')
                                    ->label('Qualquer Tipo de Documento')
                                    ->default(true)
                                    ->reactive()
                                    ->helperText('Quando ativado, a restrição se aplica a qualquer tipo de documento'),
                                
                                Select::make('document_types')
                                    ->label('Tipos de Documentos')
                                    ->options(DocType::pluck('type', 'id'))
                                    ->multiple()
                                    ->hidden(fn ($get) => $get('any_document_type'))
                                    ->helperText('Selecione os tipos específicos de documento'),
                            ]),
                            
                        TextInput::make('document_number_pattern')
                            ->label('Padrão de Número de Documento')
                            ->placeholder('Ex: 123* ou 4?6789*')
                            ->helperText('Deixe em branco para não filtrar por número. Utilize * e ? como curingas.')
                            ->maxLength(255),
                            
                        Grid::make(2)
                            ->schema([
                                Toggle::make('any_destination')
                                    ->label('Qualquer Destino')
                                    ->default(true)
                                    ->reactive()
                                    ->helperText('Quando ativado, a restrição se aplica a qualquer destino'),
                                
                                Select::make('destinations')
                                    ->label('Destinos')
                                    ->options(Destination::where('is_active', true)->pluck('name', 'id'))
                                    ->multiple()
                                    ->hidden(fn ($get) => $get('any_destination'))
                                    ->helperText('Selecione os destinos específicos'),
                            ]),
                    ]),
                    
                Section::make('Detalhes da Restrição')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Motivo da Restrição')
                            ->required()
                            ->maxLength(1000)
                            ->helperText('Descreva o motivo pelo qual este padrão deve ser restrito'),
                            
                        Select::make('severity_level')
                            ->label('Nível de Severidade')
                            ->options([
                                'none' => 'Nenhuma (Apenas Informativa)',
                                'low' => 'Baixa',
                                'medium' => 'Média',
                                'high' => 'Alta',
                            ])
                            ->default('medium')
                            ->required(),
                            
                        DateTimePicker::make('expires_at')
                            ->label('Data de Expiração')
                            ->helperText('Deixe em branco para uma restrição sem data de expiração')
                            ->timezone('America/Sao_Paulo')
                            ->displayFormat('d/m/Y H:i'),
                            
                        Toggle::make('active')
                            ->label('Ativa')
                            ->default(true)
                            ->helperText('Desative para suspender temporariamente esta restrição'),
                            
                        Toggle::make('auto_occurrence')
                            ->label('Gerar Ocorrência Automaticamente')
                            ->default(true)
                            ->helperText('Quando ativado, gera uma ocorrência automática quando um visitante corresponder a este padrão'),
                        
                        Hidden::make('created_by')
                            ->default(Auth::id()),
                    ]),
                    
                Placeholder::make('created_by')
                    ->label('Criado por')
                    ->content(fn ($record) => $record ? $record->creator->name : Auth::user()->name)
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                TextColumn::make('name_pattern')
                    ->label('Padrão de Nome')
                    ->searchable()
                    ->placeholder('Qualquer nome'),
                    
                TextColumn::make('document_number_pattern')
                    ->label('Padrão de Documento')
                    ->searchable()
                    ->placeholder('Qualquer documento'),
                    
                TextColumn::make('severity_level')
                    ->label('Severidade')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'none' => 'Nenhuma',
                        'low' => 'Baixa',
                        'medium' => 'Média',
                        'high' => 'Alta',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'none' => 'gray',
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        default => 'gray',
                    }),
                    
                IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),
                    
                IconColumn::make('auto_occurrence')
                    ->label('Auto Ocorrência')
                    ->boolean(),
                    
                TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                    
                TextColumn::make('creator.name')
                    ->label('Criado por')
                    ->searchable(),
                    
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('severity_level')
                    ->label('Severidade')
                    ->options([
                        'none' => 'Nenhuma (Apenas Informativa)',
                        'low' => 'Baixo',
                        'medium' => 'Médio',
                        'high' => 'Alto',
                    ]),
                    
                Tables\Filters\Filter::make('active')
                    ->label('Ativas')
                    ->query(fn (Builder $query) => $query->where('active', true))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('not_expired')
                    ->label('Não Expiradas')
                    ->query(fn (Builder $query) => $query->where(function ($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    }))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->active ? 'Desativar' : 'Ativar')
                    ->icon(fn ($record) => $record->active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->active ? 'danger' : 'success')
                    ->action(function ($record) {
                        $record->active = !$record->active;
                        $record->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Ativar Selecionadas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Builder $query) => $query->update(['active' => true])),
                        
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Desativar Selecionadas')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Builder $query) => $query->update(['active' => false])),
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
            'index' => Pages\ListPredictiveVisitorRestrictions::route('/'),
            'create' => Pages\CreatePredictiveVisitorRestriction::route('/create'),
            'edit' => Pages\EditPredictiveVisitorRestriction::route('/{record}/edit'),
        ];
    }
}
