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
                    ->visible(fn (DocType $record): bool => $record->visitors()->count() === 0)
                    ->before(function (Tables\Actions\DeleteAction $action, DocType $record) {
                        if ($visitorsCount = $record->visitors()->count()) {
                            // Impede a exclusão se houver visitantes associados
                            $action->cancel();
                            
                            // Notificação detalhada com o número de visitantes
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Exclusão não permitida')
                                ->body("Não é possível excluir o tipo de documento \"{$record->type}\" pois existem {$visitorsCount} visitante(s) associado(s) a ele.")
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Deletar Tipos de Documentos')
                        ->modalDescription('Tem certeza que deseja deletar os tipos de documentos selecionados?')
                        ->modalSubmitActionLabel('Sim, deletar')
                        ->visible(function (): bool {
                            // Verifica se existem tipos de documentos que podem ser excluídos
                            return DocType::whereDoesntHave('visitors')->exists();
                        })
                        ->before(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            $blockedRecords = [];
                            
                            foreach ($records as $record) {
                                if ($visitorsCount = $record->visitors()->count()) {
                                    $blockedRecords[] = [
                                        'name' => $record->type,
                                        'count' => $visitorsCount
                                    ];
                                }
                            }
                            
                            if (!empty($blockedRecords)) {
                                $action->cancel();
                                
                                // Mensagem detalhada indicando quais tipos não podem ser excluídos
                                $blockedDetails = collect($blockedRecords)
                                    ->map(fn ($item) => "- {$item['name']} ({$item['count']} visitante(s))")
                                    ->join('<br>');
                                
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Exclusão não permitida')
                                    ->body(new \Illuminate\Support\HtmlString(
                                        "Não é possível excluir os seguintes tipos de documentos pois possuem visitantes associados:<br>{$blockedDetails}"
                                    ))
                                    ->persistent()
                                    ->send();
                            }
                        }),
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