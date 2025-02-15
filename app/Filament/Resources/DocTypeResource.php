<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocTypeResource\Pages;
use App\Models\DocType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocTypeResource extends Resource
{
    protected static ?string $model = DocType::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationGroup = 'Controle de Acesso';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Tipo de Documento';
    
    protected static ?string $pluralModelLabel = 'Tipos de Documentos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do Tipo de Documento')
                    ->schema([
                        Forms\Components\TextInput::make('type')
                            ->label('Tipo')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                            
                        Forms\Components\Toggle::make('is_default')
                            ->label('Tipo Padrão')
                            ->helperText('Se marcado, este será o tipo de documento padrão ao cadastrar visitantes.')
                            ->default(false),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Tipo Padrão')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('visitors_count')
                    ->label('Visitantes')
                    ->counts('visitors')
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
                    ->modalHeading('Deletar Tipo de Documento')
                    ->modalDescription(fn (DocType $record): string => 
                        "Tem certeza que deseja deletar o tipo de documento \"{$record->type}\"?"
                    )
                    ->modalSubmitActionLabel('Sim, deletar')
                    ->before(function (DocType $record) {
                        if ($record->visitors()->count() > 0) {
                            // Impede a exclusão se houver visitantes associados
                            return false;
                        }
                    })
                    ->failureNotification(
                        notification: fn (DocType $record) => 
                            "Não é possível excluir o tipo de documento \"{$record->type}\" pois existem visitantes associados a ele."
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Deletar Tipos de Documentos')
                        ->modalDescription('Tem certeza que deseja deletar os tipos de documentos selecionados?')
                        ->modalSubmitActionLabel('Sim, deletar')
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->visitors()->count() > 0) {
                                    // Impede a exclusão em massa se houver visitantes associados
                                    return false;
                                }
                            }
                        })
                        ->failureNotification(fn () => 
                            'Não é possível excluir tipos de documentos que possuem visitantes associados.'
                        ),
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
            'index' => Pages\ListDocTypes::route('/'),
            'create' => Pages\CreateDocType::route('/create'),
            'edit' => Pages\EditDocType::route('/{record}/edit'),
        ];
    }
} 