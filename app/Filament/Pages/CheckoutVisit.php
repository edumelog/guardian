<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Illuminate\Support\Facades\Gate;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use App\Models\Visitor;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Support\Enums\MaxWidth;

class CheckoutVisit extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;
    
    protected static ?string $navigationIcon = 'heroicon-o-arrow-left-circle';
    protected static ?string $navigationLabel = 'Registro de Saída';
    protected static ?string $title = 'Registro de Saída de Visitantes';
    protected static ?string $slug = 'checkout-visit';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = 'Controle de Acesso';
    protected static string $view = 'filament.pages.checkout-visit';
    // Max width
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    public $quickCheckoutDoc = '';

    public function quickCheckout(): void
    {
        // Verifica se o valor inserido é um número
        if (!is_numeric($this->quickCheckoutDoc)) {
            Notification::make()
                ->warning()
                ->title('Formato inválido')
                ->body('Por favor, digite apenas números para o ID da visita.')
                ->send();
            return;
        }

        // Busca a visita pelo ID
        $visitorLog = \App\Models\VisitorLog::find($this->quickCheckoutDoc);

        if (!$visitorLog) {
            Notification::make()
                ->warning()
                ->title('Visita não encontrada')
                ->body('Nenhuma visita encontrada com este ID.')
                ->send();
            return;
        }

        // Verifica se a visita já tem registro de saída
        if ($visitorLog->out_date !== null) {
            Notification::make()
                ->warning()
                ->title('Saída já registrada')
                ->body("Esta visita já possui registro de saída em " . $visitorLog->out_date->format('d/m/Y H:i') . ".")
                ->send();
            return;
        }

        // Busca o visitante associado à visita
        $visitor = $visitorLog->visitor;

        // Registra a saída
        $visitorLog->update([
            'out_date' => now()
        ]);

        // Registra ocorrência se necessário
        $occurrenceService = new \App\Services\OccurrenceService();
        $occurrenceService->registerExitOccurrence($visitor, $visitorLog);

        // Limpa o campo
        $this->quickCheckoutDoc = '';

        // Notifica o sucesso
        Notification::make()
            ->success()
            ->title('Saída registrada')
            ->body("Saída registrada com sucesso para o visitante {$visitor->name}.")
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Models\VisitorLog::query()
                    ->select('visitor_logs.*')
                    ->join('visitors', 'visitor_logs.visitor_id', '=', 'visitors.id')
                    ->whereNull('visitor_logs.out_date')
                    ->with(['visitor', 'visitor.docType', 'destination'])
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID da Visita')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('visitor.name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('visitors.name', $direction);
                    }),
                TextColumn::make('visitor.doc')
                    ->label('Documento')
                    ->searchable(),
                TextColumn::make('visitor.docType.type')
                    ->label('Tipo'),
                TextColumn::make('destination.name')
                    ->label('Local')
                    ->searchable(),
                TextColumn::make('in_date')
                    ->label('Entrada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('in_date', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('destination')
                    ->label('Local')
                    ->relationship('destination', 'name')
                    ->preload(),
                \Filament\Tables\Filters\SelectFilter::make('docType')
                    ->label('Tipo de Documento')
                    ->relationship('visitor.docType', 'type')
                    ->preload(),
                \Filament\Tables\Filters\Filter::make('in_date')
                    ->label('Data de Entrada')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('data_entrada_de')
                            ->label('Data de Entrada (de)')
                            ->placeholder('De'),
                        \Filament\Forms\Components\DatePicker::make('data_entrada_ate')
                            ->label('Data de Entrada (até)')
                            ->placeholder('Até'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['data_entrada_de'],
                                fn ($query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('in_date', '>=', $date),
                            )
                            ->when(
                                $data['data_entrada_ate'],
                                fn ($query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('in_date', '<=', $date),
                            );
                    })
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('checkout')
                    ->label('Registrar Saída')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->action(function (\App\Models\VisitorLog $record) {
                        if (!$record->out_date) {
                            $record->update(['out_date' => now()]);
                            
                            // Registra ocorrência se necessário
                            $occurrenceService = new \App\Services\OccurrenceService();
                            $occurrenceService->registerExitOccurrence($record->visitor, $record);
                            
                            Notification::make()
                                ->success()
                                ->title('Saída registrada')
                                ->body("Saída registrada com sucesso para o visitante {$record->visitor->name}.")
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Registrar Saída')
                    ->modalDescription('Tem certeza que deseja registrar a saída deste visitante?')
                    ->modalSubmitActionLabel('Sim, registrar saída')
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkAction::make('bulk_checkout')
                    ->label('Registrar Saída em Massa')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $count = 0;
                        $occurrenceService = new \App\Services\OccurrenceService();
                        
                        foreach ($records as $record) {
                            if (!$record->out_date) {
                                $record->update(['out_date' => now()]);
                                
                                // Registra ocorrência se necessário
                                $occurrenceService->registerExitOccurrence($record->visitor, $record);
                                
                                $count++;
                            }
                        }
                        
                        Notification::make()
                            ->success()
                            ->title('Saídas registradas')
                            ->body("Saída registrada com sucesso para {$count} visitante(s).")
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Registrar Saída em Massa')
                    ->modalDescription('Tem certeza que deseja registrar a saída para todos os visitantes selecionados?')
                    ->modalSubmitActionLabel('Sim, registrar saídas')
                    ->deselectRecordsAfterCompletion()
            ])
            ->emptyStateHeading('Nenhuma visita em andamento')
            ->emptyStateDescription('Não há visitantes com visitas em andamento no momento.')
            ->poll('10s');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Gate::allows('page_CheckoutVisit');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        abort_unless(Gate::allows('page_CheckoutVisit'), 403);
    }

    public function getMaxWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }
} 