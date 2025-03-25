<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitorRestrictionResource\Pages;
use App\Models\VisitorRestriction;
use App\Models\Visitor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Colors\Color;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\Facades\Log;

class VisitorRestrictionResource extends Resource
{
    protected static ?string $model = VisitorRestriction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationLabel = 'Restrições';
    protected static ?string $modelLabel = 'Restrição';
    protected static ?string $pluralModelLabel = 'Restrições';
    protected static ?string $navigationGroup = 'Análise de Segurança';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('visitor_id'),
                Forms\Components\Hidden::make('visitor_photo'),
                Forms\Components\Hidden::make('visitor_doc_photo_front'),
                Forms\Components\Hidden::make('visitor_doc_photo_back'),

                Forms\Components\Section::make('Dados do Visitante')
                    ->schema([
                        Forms\Components\TextInput::make('visitor_doc_type')
                            ->label('Tipo de Documento')
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('visitor_doc')
                            ->label('Número do Documento')
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('visitor_name')
                            ->label('Nome')
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('visitor_phone')
                            ->label('Telefone')
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\ViewField::make('visitor_photo')
                                    ->label('Foto')
                                    ->view('filament.forms.components.visitor-photo')
                                    ->extraAttributes(['style' => 'aspect-ratio: 9/16; height: auto;'])
                                    ->columnSpan(1),

                                Forms\Components\ViewField::make('visitor_doc_photo_front')
                                    ->label('Documento - Frente')
                                    ->view('filament.forms.components.visitor-doc-photos')
                                    ->columnSpan(1),

                                Forms\Components\ViewField::make('visitor_doc_photo_back')
                                    ->label('Documento - Verso')
                                    ->view('filament.forms.components.visitor-doc-photos')
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->columnSpan('full'),

                        Forms\Components\Section::make('Última Visita')
                            ->schema([
                                Forms\Components\TextInput::make('visitor_destination')
                                    ->label('Local')
                                    ->disabled()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('visitor_last_visit')
                                    ->label('Data e Hora')
                                    ->disabled()
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                            ->columnSpan('full'),
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nome'),

                Tables\Columns\TextColumn::make('doc')
                    ->searchable()
                    ->sortable()
                    ->label('Documento'),

                Tables\Columns\TextColumn::make('docType.type')
                    ->sortable()
                    ->label('Tipo de Documento'),

                Tables\Columns\TextColumn::make('severity_level')
                    ->badge()
                    ->color(fn (Model $record): string => match ($record->severity_level) {
                        'low' => 'warning',
                        'medium' => 'orange',
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
                    ->visible(fn (VisitorRestriction $record) => $record->active)
                    ->action(fn (VisitorRestriction $record) => $record->deactivate())
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
            'index' => Pages\ListVisitorRestrictions::route('/'),
            'create' => Pages\CreateVisitorRestriction::route('/create'),
            'new' => Pages\NewVisitorRestriction::route('/new'),
            'edit' => Pages\EditVisitorRestriction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['visitor', 'docType', 'creator']);
    }
}
