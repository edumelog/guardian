<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartialVisitorRestrictionResource\Pages;
use App\Models\PartialVisitorRestriction;
use App\Models\DocType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;

class PartialVisitorRestrictionResource extends Resource
{
    protected static ?string $model = PartialVisitorRestriction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationLabel = 'Restrições Preditivas';
    protected static ?string $modelLabel = 'Restrição Preditiva';
    protected static ?string $pluralModelLabel = 'Restrições Preditivas';
    protected static ?string $navigationGroup = 'Análise de Segurança';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Visitante')
                    ->description('Preencha pelo menos um dos campos: Nome, Documento ou Telefone. Use * (asterisco) para substituir qualquer quantidade de caracteres e ? (interrogação) para substituir um caractere único.')
                    ->schema([
                        Forms\Components\Select::make('doc_type_id')
                            ->label('Tipo de Documento')
                            ->options(function() {
                                // Obtém todos os tipos de documento
                                $docTypes = DocType::all()->pluck('type', 'id')->toArray();
                                
                                // Adiciona a opção "Todos" no início
                                return [null => ''] + $docTypes;
                            })
                            ->searchable()
                            ->nullable()
                            ->helperText('Selecione ou deixe em branco para todos')
                            ->placeholder('Selecione ou deixe em branco para todos'),
                            
                        Forms\Components\TextInput::make('partial_doc')
                            ->label('Documento (Parcial)')
                            ->placeholder('Ex: 123*.4??.*56')
                            ->helperText('Exemplos: "123" = exatamente 123; "123*" = começa com 123; "*123" = termina com 123; "*123*" = contém 123')
                            ->nullable()
                            ->regex('/^[A-Z0-9\.\-\*\?\s]+$/')
                            ->validationMessages([
                                'regex' => 'Use apenas números, letras maiúsculas, pontos, traços, asteriscos (*), interrogações (?) e espaços.',
                            ])
                            ->dehydrateStateUsing(fn ($state) => $state ? mb_strtoupper($state) : null)
                            ->afterStateUpdated(fn (callable $set, $state) => $set('partial_doc', $state ? mb_strtoupper($state) : null))
                            ->extraInputAttributes(['style' => 'text-transform: uppercase;'])
                            ->live(),
                            
                        Forms\Components\TextInput::make('partial_name')
                            ->label('Nome (Parcial)')
                            ->placeholder('Ex: JOÃO* ou *SILVA')
                            ->helperText('Exemplos: "MELO" = exatamente MELO; "MELO*" = começa com MELO; "*MELO" = termina com MELO; "*MELO*" = contém MELO')
                            ->nullable()
                            ->regex('/^[A-Z\.\-\'\*\?\s]+$/')
                            ->validationMessages([
                                'regex' => 'Use apenas letras maiúsculas, pontos, traços, apóstrofos, asteriscos (*), interrogações (?) e espaços.',
                            ])
                            ->dehydrateStateUsing(fn ($state) => $state ? mb_strtoupper($state) : null)
                            ->afterStateUpdated(fn (callable $set, $state) => $set('partial_name', $state ? mb_strtoupper($state) : null))
                            ->extraInputAttributes(['style' => 'text-transform: uppercase;'])
                            ->live(),
                            
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone (Parcial)')
                            ->placeholder('Ex: (21)????-5678')
                            ->helperText('Exemplos: "5678" = exatamente 5678; "5678*" = começa com 5678; "*5678" = termina com 5678; ? = substitui um único caractere')
                            ->nullable()
                            ->regex('/^[0-9\(\)\-\+\*\?\s]+$/')
                            ->validationMessages([
                                'regex' => 'Use apenas números, parênteses, traços, sinal de mais, asteriscos (*), interrogações (?) e espaços.',
                            ])
                            ->extraInputAttributes(['style' => 'text-transform: uppercase;']),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dados da Restrição')
                    ->schema([
                        Forms\Components\Select::make('severity_level')
                            ->options([
                                'low' => 'Baixa',
                                'medium' => 'Média',
                                'high' => 'Alta',
                            ])
                            ->required()
                            ->default('medium')
                            ->label('Nível de Severidade'),

                        Forms\Components\Textarea::make('reason')
                            ->required()
                            ->maxLength(65535)
                            ->label('Motivo'),

                        Forms\Components\DatePicker::make('expires_at')
                            ->label('Data de Expiração')
                            ->minDate(now())
                            ->timezone('America/Sao_Paulo')
                            ->displayFormat('d M Y')
                            ->format('Y-m-d')
                            ->native(false)
                            ->nullable()
                            ->placeholder('Nunca')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('clear')
                                    ->icon('heroicon-m-x-mark')
                                    ->action(function (Forms\Set $set) {
                                        $set('expires_at', null);
                                    })
                            )
                            ->dehydrateStateUsing(fn ($state) => $state ? now()->parse($state)->endOfDay() : null),

                        Forms\Components\Toggle::make('active')
                            ->label('Ativo')
                            ->default(true)
                            ->required(),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => Auth::user()->id),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partial_name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Qualquer nome')
                    ->label('Nome Parcial'),

                Tables\Columns\TextColumn::make('partial_doc')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Todos')
                    ->label('Num. Doc.'),

                Tables\Columns\TextColumn::make('docType.type')
                    ->sortable()
                    ->placeholder('Todos')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->doc_type_id ? $state : 'Todos';
                    })
                    ->label('Tipo Doc.'),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Todos')
                    ->label('Telefone'),

                Tables\Columns\TextColumn::make('severity_level')
                    ->badge()
                    ->color(fn (Model $record): string => match ($record->severity_level) {
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'low' => 'Baixa',
                        'medium' => 'Média',
                        'high' => 'Alta',
                        default => 'Média',
                    })
                    ->sortable()
                    ->label('Severidade'),

                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable()
                    ->label('Ativo'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->date('d M Y')
                    ->placeholder('Nunca')
                    ->sortable()
                    ->label('Expira em'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->sortable()
                    ->label('Criado por'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->label('Criado em'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('severity_level')
                    ->options([
                        'low' => 'Baixa',
                        'medium' => 'Média',
                        'high' => 'Alta',
                    ])
                    ->label('Severidade'),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Ativo'),

                Tables\Filters\Filter::make('expired')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<', now()))
                    ->label('Expirado'),

                Tables\Filters\Filter::make('not_expired')
                    ->query(fn (Builder $query): Builder => $query->where(function($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    }))
                    ->label('Não Expirado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PartialVisitorRestriction $record) => $record->active)
                    ->action(fn (PartialVisitorRestriction $record) => $record->deactivate())
                    ->label('Desativar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListPartialVisitorRestrictions::route('/'),
            'create' => Pages\CreatePartialVisitorRestriction::route('/create'),
            'edit' => Pages\EditPartialVisitorRestriction::route('/{record}/edit'),
        ];
    }
    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['docType', 'creator']);
    }
}
