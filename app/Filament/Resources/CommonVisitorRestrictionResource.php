<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommonVisitorRestrictionResource\Pages;
use App\Models\CommonVisitorRestriction;
use App\Models\DocType;
use App\Models\Visitor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class CommonVisitorRestrictionResource extends Resource 
{
    protected static ?string $model = CommonVisitorRestriction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Análise de Segurança';

    protected static ?string $navigationLabel = 'Restrição Comum';

    protected static ?string $pluralModelLabel = 'Restrições Comuns';

    protected static ?string $modelLabel = 'Restrição Comum';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações da Restrição')
                    ->schema([
                        Forms\Components\Select::make('visitor_id')
                            ->label('Visitante')
                            ->relationship('visitor', 'name')
                            ->searchable(['name', 'doc'])
                            ->getSearchResultsUsing(function (string $search) {
                                return Visitor::where('name', 'like', "%{$search}%")
                                    ->orWhere('doc', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($visitor) => [
                                        $visitor->id => "[{$visitor->doc}] {$visitor->name}"
                                    ])
                                    ->toArray();
                            })
                            ->preload()
                            ->required()
                            ->helperText('Busque por nome ou número do documento do visitante')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                if ($state) {
                                    $visitor = Visitor::find($state);
                                    
                                    // Verifica se é uma edição (tem ID) ou criação
                                    $currentId = $get('id');
                                    
                                    $existingRestriction = CommonVisitorRestriction::where('visitor_id', $state)
                                        ->where('active', true)
                                        ->when($currentId, function ($query) use ($currentId) {
                                            return $query->where('id', '!=', $currentId);
                                        })
                                        ->first();
                                        
                                    if ($existingRestriction) {
                                        // Exibe notificação informativa, mas não impede a criação/edição
                                        \Filament\Notifications\Notification::make()
                                            ->warning()
                                            ->title('Visitante já possui restrição ativa')
                                            ->body("Este visitante já possui uma restrição ativa. Se desejar criar uma nova restrição, deixe-a desativada ou desative a existente antes.")
                                            ->actions([
                                                \Filament\Notifications\Actions\Action::make('view')
                                                    ->label('Ver Restrição Ativa')
                                                    ->url(route('filament.dashboard.resources.common-visitor-restrictions.edit', $existingRestriction))
                                                    ->button(),
                                            ])
                                            ->send();
                                    }
                                    
                                    if ($visitor) {
                                        $set('visitor_name', $visitor->name);
                                        $set('visitor_doc', $visitor->doc);
                                        $set('visitor_doc_type', $visitor->docType->type ?? 'Não especificado');
                                    }
                                } else {
                                    $set('visitor_name', null);
                                    $set('visitor_doc', null);
                                    $set('visitor_doc_type', null);
                                }
                            }),
                            
                        Forms\Components\Placeholder::make('visitor_name')
                            ->label('Nome do Visitante')
                            ->content(fn ($get) => $get('visitor_name') ?? '-')
                            ->visible(fn ($get) => filled($get('visitor_id'))),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Placeholder::make('visitor_doc')
                                    ->label('Documento')
                                    ->content(fn ($get) => $get('visitor_doc') ?? '-')
                                    ->visible(fn ($get) => filled($get('visitor_id'))),

                                Forms\Components\Placeholder::make('visitor_doc_type')
                                    ->label('Tipo de Documento')
                                    ->content(fn ($get) => $get('visitor_doc_type') ?? '-')
                                    ->visible(fn ($get) => filled($get('visitor_id'))),
                            ]),

                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo da Restrição')
                            ->required()
                            ->rows(3),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('severity_level')
                                    ->label('Nível de Severidade')
                                    ->options(CommonVisitorRestriction::SEVERITY_LEVELS)
                                    ->required()
                                    ->default('none'),

                                Forms\Components\DatePicker::make('expires_at')
                                    ->label('Data de Expiração')
                                    ->nullable()
                                    ->default(null)
                                    ->minDate(now())
                                    ->helperText('Deixe em branco para não expirar'),
                            ]),

                        Forms\Components\Toggle::make('active')
                            ->label('Ativo')
                            ->helperText('Indica se esta restrição está ativa')
                            ->default(true),

                        Forms\Components\Hidden::make('created_by')
                            ->default(Auth::id())
                            ->dehydrated(fn ($state) => filled($state)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('visitor.name')
                    ->label('Visitante')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('visitor.doc')
                    ->label('Documento')
                    ->searchable(),

                Tables\Columns\TextColumn::make('visitor.docType.type')
                    ->label('Tipo Doc.')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(30)
                    ->tooltip(function (CommonVisitorRestriction $record): string {
                        return $record->reason;
                    }),

                Tables\Columns\BadgeColumn::make('severity_level')
                    ->label('Severidade')
                    ->formatStateUsing(fn (string $state): string => CommonVisitorRestriction::SEVERITY_LEVELS[$state] ?? $state)
                    ->colors([
                        'success' => fn ($state) => $state === 'low',
                        'warning' => fn ($state) => $state === 'medium',
                        'danger' => fn ($state) => $state === 'high',
                    ]),

                Tables\Columns\IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Nunca'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Criado Por')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado Em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('severity_level')
                    ->label('Severidade')
                    ->options(CommonVisitorRestriction::SEVERITY_LEVELS),

                Tables\Filters\Filter::make('active')
                    ->label('Apenas Ativos')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->active()),

                Tables\Filters\Filter::make('expires_at')
                    ->label('Período de Validade')
                    ->form([
                        Forms\Components\DatePicker::make('expires_from')
                            ->label('Expira Após'),
                        Forms\Components\DatePicker::make('expires_until')
                            ->label('Expira Até'),
                        Forms\Components\Checkbox::make('show_not_expires')
                            ->label('Incluir Sem Expiração')
                            ->default(true),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['expires_from'],
                                fn (Builder $query, $date): Builder => $query->where('expires_at', '>=', $date),
                            )
                            ->when(
                                $data['expires_until'],
                                fn (Builder $query, $date): Builder => $query->where('expires_at', '<=', $date),
                            )
                            ->when(
                                $data['show_not_expires'] && ($data['expires_from'] || $data['expires_until']),
                                fn (Builder $query): Builder => $query->orWhereNull('expires_at'),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (CommonVisitorRestriction $record): string => $record->active ? 'Desativar' : 'Ativar')
                    ->icon(fn (CommonVisitorRestriction $record): string => $record->active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (CommonVisitorRestriction $record): string => $record->active ? 'danger' : 'success')
                    ->action(function (CommonVisitorRestriction $record): void {
                        // Se estiver tentando ativar uma restrição, verifica se já existe alguma ativa para este visitante
                        if (!$record->active) {
                            $existingRestriction = CommonVisitorRestriction::where('visitor_id', $record->visitor_id)
                                ->where('active', true)
                                ->where('id', '!=', $record->id)
                                ->first();
                                
                            if ($existingRestriction) {
                                // Exibe notificação e interrompe a ativação
                                Notification::make()
                                    ->danger()
                                    ->title('Não foi possível ativar a restrição')
                                    ->body('Este visitante já possui uma restrição ativa. Desative-a antes de ativar esta.')
                                    ->persistent()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('view')
                                            ->label('Ver Restrição Ativa')
                                            ->url(route('filament.dashboard.resources.common-visitor-restrictions.edit', $existingRestriction))
                                            ->button(),
                                    ])
                                    ->send();
                                    
                                return;
                            }
                        }
                        
                        // Se não há problema, continua com a ação de ativar/desativar
                        $record->active = !$record->active;
                        $record->save();
                        
                        // Atualizar o campo has_restrictions do visitante, se aplicável
                        if ($record->visitor_id) {
                            $visitor = Visitor::find($record->visitor_id);
                            if ($visitor) {
                                $visitor->updateHasRestrictions();
                            }
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Ativar Selecionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $visitorIds = [];
                            $skippedRecords = 0;
                            
                            foreach ($records as $record) {
                                // Verifica se já existe uma restrição ativa para este visitante
                                $existingRestriction = CommonVisitorRestriction::where('visitor_id', $record->visitor_id)
                                    ->where('active', true)
                                    ->where('id', '!=', $record->id)
                                    ->first();
                                    
                                if ($existingRestriction) {
                                    // Pula este registro
                                    $skippedRecords++;
                                    continue;
                                }
                                
                                // Se não há restrição ativa existente, ativa esta
                                $record->active = true;
                                $record->save();
                                
                                if ($record->visitor_id) {
                                    $visitorIds[] = $record->visitor_id;
                                }
                            }
                            
                            // Atualizar o campo has_restrictions dos visitantes, se aplicável
                            foreach (array_unique($visitorIds) as $visitorId) {
                                $visitor = Visitor::find($visitorId);
                                if ($visitor) {
                                    $visitor->updateHasRestrictions();
                                }
                            }
                            
                            // Se algum registro foi pulado, exibe uma notificação
                            if ($skippedRecords > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Algumas restrições não foram ativadas')
                                    ->body("$skippedRecords " . ($skippedRecords == 1 ? 'restrição não foi ativada' : 'restrições não foram ativadas') . " porque " . ($skippedRecords == 1 ? 'o visitante já possui' : 'os visitantes já possuem') . " uma restrição ativa.")
                                    ->send();
                            }
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Desativar Selecionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (Collection $records): void {
                            $visitorIds = [];
                            foreach ($records as $record) {
                                $record->active = false;
                                $record->save();
                                
                                if ($record->visitor_id) {
                                    $visitorIds[] = $record->visitor_id;
                                }
                            }
                            
                            // Atualizar o campo has_restrictions dos visitantes, se aplicável
                            foreach (array_unique($visitorIds) as $visitorId) {
                                $visitor = Visitor::find($visitorId);
                                if ($visitor) {
                                    $visitor->updateHasRestrictions();
                                }
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
            'index' => Pages\ListCommonVisitorRestrictions::route('/'),
            'create' => Pages\CreateCommonVisitorRestriction::route('/create'),
            'edit' => Pages\EditCommonVisitorRestriction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['visitor', 'docType', 'creator']);
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
