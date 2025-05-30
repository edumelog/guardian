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
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Columns\TextColumn;

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
        $record = $form->getRecord();
        $hasVisits = $record ? $record->visitorLogs()->exists() : false;

        return $form
            ->schema([
                Section::make('Informações do Destino')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->disabled($hasVisits)
                            ->helperText($hasVisits ? 'O nome não pode ser alterado pois este destino possui visitas registradas.' : null),
                            
                        TextInput::make('alias')
                            ->label('Sigla')
                            ->maxLength(5)
                            ->placeholder('Ex: ANEXO')
                            ->helperText('Máximo de 5 caracteres em maiúsculas')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                if ($state) {
                                    $set('alias', strtoupper($state));
                                }
                            }),
                            
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
                                    // Se estiver editando, exclui o próprio registro e seus filhos
                                    if ($record) {
                                        $childrenIds = $record->getAllChildrenIds();
                                        $query->whereNotIn('id', [...$childrenIds, $record->id]);
                                    }

                                    // Sempre mostra apenas destinos ativos como opções de pai
                                    $query->where('is_active', true);

                                    return $query;
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('Selecione o destino pai (opcional)')
                            ->afterStateHydrated(function ($component, $state, $record) {
                                // Se o pai atual está inativo, limpa a seleção
                                if ($state && $record) {
                                    $parent = \App\Models\Destination::find($state);
                                    if ($parent && !$parent->is_active) {
                                        $component->state(null);
                                        
                                        // Notifica o usuário sobre a mudança
                                        \Filament\Notifications\Notification::make()
                                            ->warning()
                                            ->title('Destino pai removido')
                                            ->body('O destino pai foi removido pois está inativo.')
                                            ->persistent()
                                            ->send();
                                    }
                                }
                            }),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Ativo')
                            ->default(true)
                            ->helperText('Destinos inativos não aparecem no cadastro de visitantes'),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                    
                TextColumn::make('alias')
                    ->label('Sigla')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('address')
                    ->label('Endereço')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Ativo',
                        '0' => 'Inativo',
                    ])
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Deletar Destino')
                    ->modalDescription(function (Destination $record): string {
                        // Verifica se tem ocorrências associadas
                        if ($record->hasOccurrences()) {
                            return "Não é possível excluir o destino \"{$record->name}\" pois existem ocorrências associadas a ele.";
                        }
                        
                        // Verifica se tem visitas na hierarquia
                        if ($record->hasVisitsInHierarchy()) {
                            $message = "Não é possível excluir o destino \"{$record->name}\"";
                            
                            // Se o próprio destino tem visitas
                            if ($record->visitorLogs()->exists()) {
                                $message .= " pois ele possui visitas registradas";
                            } else {
                                $message .= " pois um ou mais de seus subdestinos possuem visitas registradas";
                            }
                            
                            return $message . ".";
                        }

                        // Verifica se tem subdestinos
                        $childrenCount = $record->children()->count();
                        if ($childrenCount > 0) {
                            return "O destino \"{$record->name}\" possui {$childrenCount} subdestino(s) associado(s). Ao deletar este destino, todos os subdestinos também serão removidos. Deseja continuar?";
                        }

                        return "Tem certeza que deseja deletar o destino \"{$record->name}\"?";
                    })
                    ->modalSubmitActionLabel('Sim, deletar')
                    // Esconde o botão de confirmação quando não for possível excluir
                    ->hidden(fn (Destination $record) => $record->hasVisitsInHierarchy() || $record->hasOccurrences())
                    ->action(function (Destination $record) {
                        // Verifica se o destino possui ocorrências associadas
                        if ($record->hasOccurrences()) {
                            Notification::make()
                                ->danger()
                                ->title('Exclusão não permitida')
                                ->body("Não é possível excluir o destino \"{$record->name}\" pois existem ocorrências associadas a ele.")
                                ->persistent()
                                ->send();
                            return;
                        }
                        
                        // Verifica se o destino ou seus filhos têm visitas
                        if ($record->hasVisitsInHierarchy()) {
                            $message = 'Não é possível excluir um destino que ';
                            if ($record->visitorLogs()->exists()) {
                                $message .= 'possui visitas registradas';
                            } else {
                                $message .= 'possui subdestinos com visitas registradas';
                            }

                            Notification::make()
                                ->danger()
                                ->title('Exclusão não permitida')
                                ->body($message)
                                ->send();
                            return;
                        }

                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Destino excluído')
                            ->body('O destino foi excluído com sucesso.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Deletar Destinos')
                        ->modalDescription(function ($action): string {
                            $destinationsWithChildren = [];
                            $destinationsWithVisits = [];
                            $destinationsWithChildVisits = [];
                            $destinationsWithOccurrences = [];
                            $canDeleteAny = false;

                            $records = $action->getRecords();
                            if (!$records) {
                                return 'Tem certeza que deseja deletar os destinos selecionados?';
                            }

                            foreach ($records as $record) {
                                if ($record->hasOccurrences()) {
                                    $destinationsWithOccurrences[] = $record->name;
                                } else if ($record->visitorLogs()->exists()) {
                                    $destinationsWithVisits[] = $record->name;
                                } else if ($record->hasVisitsInHierarchy()) {
                                    $destinationsWithChildVisits[] = $record->name;
                                } else if ($record->children()->count() > 0) {
                                    $destinationsWithChildren[] = $record->name;
                                    $canDeleteAny = true;
                                } else {
                                    $canDeleteAny = true;
                                }
                            }

                            $modalDescription = '';

                            // Primeiro, mostrar destinos com ocorrências
                            if (!empty($destinationsWithOccurrences)) {
                                $modalDescription .= '<div class="mb-3 text-red-600"><strong>Não será possível excluir os seguintes destinos pois possuem ocorrências associadas:</strong><ul class="list-disc pl-5 mt-1">';
                                foreach ($destinationsWithOccurrences as $name) {
                                    $modalDescription .= '<li>' . e($name) . '</li>';
                                }
                                $modalDescription .= '</ul></div>';
                            }

                            // Depois, mostrar destinos com visitas
                            if (!empty($destinationsWithVisits)) {
                                $visitsList = implode('", "', $destinationsWithVisits);
                                $modalDescription .= "ATENÇÃO: Os seguintes destinos não podem ser excluídos pois possuem visitas registradas:\n\n\"{$visitsList}\"\n\n";
                            }

                            if (!empty($destinationsWithChildVisits)) {
                                $childVisitsList = implode('", "', $destinationsWithChildVisits);
                                $modalDescription .= "ATENÇÃO: Os seguintes destinos não podem ser excluídos pois possuem subdestinos com visitas registradas:\n\n\"{$childVisitsList}\"\n\n";
                            }
                            
                            if (!empty($destinationsWithChildren)) {
                                $childrenList = implode('", "', $destinationsWithChildren);
                                $modalDescription .= "ATENÇÃO: Os seguintes destinos possuem subdestinos associados:\n\n\"{$childrenList}\"\n\nAo deletar estes destinos, todos os seus subdestinos também serão removidos.";
                            }

                            if ($modalDescription) {
                                if (!$canDeleteAny) {
                                    return $modalDescription . "\n\nNenhum dos destinos selecionados pode ser excluído.";
                                }
                                return $modalDescription . "\n\nDeseja continuar com a exclusão dos destinos permitidos?";
                            }

                            return 'Tem certeza que deseja deletar os destinos selecionados?';
                        })
                        ->modalSubmitActionLabel('Sim, deletar')
                        ->disabled(function ($action): bool {
                            $records = $action->getRecords();
                            if (!$records) {
                                return false;
                            }

                            foreach ($records as $record) {
                                if (!$record->hasVisitsInHierarchy()) {
                                    return false; // Habilita o botão se pelo menos um destino puder ser excluído
                                }
                            }
                            return true; // Desabilita o botão se nenhum destino puder ser excluído
                        })
                        ->before(function (Tables\Actions\DeleteBulkAction $action, Collection $records) {
                            $destinationsToKeep = [];

                            foreach ($records as $index => $record) {
                                // Impede a exclusão de destinos com ocorrências
                                if ($record->hasOccurrences()) {
                                    $destinationsToKeep[] = $record->name;
                                    $records->forget($index);
                                    continue;
                                }
                                
                                // Impede a exclusão de destinos com visitas
                                if ($record->hasVisitsInHierarchy()) {
                                    $destinationsToKeep[] = $record->name;
                                    $records->forget($index);
                                }
                            }

                            // Se nenhum destino pode ser excluído, cancela a ação
                            if ($records->isEmpty()) {
                                $action->cancel();
                                
                                Notification::make()
                                    ->danger()
                                    ->title('Exclusão não permitida')
                                    ->body('Nenhum dos destinos selecionados pode ser excluído, pois todos possuem ocorrências ou visitas associadas.')
                                    ->persistent()
                                    ->send();
                            } elseif (!empty($destinationsToKeep)) {
                                // Se alguns destinos não podem ser excluídos, notifica o usuário
                                $action->cancel();

                                // Formatação mais clara da mensagem
                                $destinationsToKeepList = collect($destinationsToKeep)
                                    ->map(fn ($name) => "- {$name}")
                                    ->join('<br>');

                                Notification::make()
                                    ->warning()
                                    ->title('Atenção: Exclusão parcial')
                                    ->body(new \Illuminate\Support\HtmlString(
                                        "Os seguintes destinos não podem ser excluídos pois possuem ocorrências ou visitas associadas:<br>{$destinationsToKeepList}<br><br>Por favor, selecione apenas destinos que possam ser excluídos."
                                    ))
                                    ->persistent()
                                    ->send();
                            }
                        })
                        ->action(function (Collection $records) {
                            $hasVisits = false;
                            $hasChildVisits = false;
                            $deletedCount = 0;

                            foreach ($records as $record) {
                                if ($record->visitorLogs()->exists()) {
                                    $hasVisits = true;
                                    continue;
                                }

                                if ($record->hasVisitsInHierarchy()) {
                                    $hasChildVisits = true;
                                    continue;
                                }
                                
                                $record->delete();
                                $deletedCount++;
                            }

                            if ($hasVisits) {
                                Notification::make()
                                    ->warning()
                                    ->title('Alguns destinos não foram excluídos')
                                    ->body('Destinos que possuem visitas registradas não podem ser excluídos.')
                                    ->send();
                            }

                            if ($hasChildVisits) {
                                Notification::make()
                                    ->warning()
                                    ->title('Alguns destinos não foram excluídos')
                                    ->body('Destinos que possuem subdestinos com visitas registradas não podem ser excluídos.')
                                    ->send();
                            }

                            if ($deletedCount > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Destinos excluídos')
                                    ->body("{$deletedCount} destinos foram excluídos com sucesso.")
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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