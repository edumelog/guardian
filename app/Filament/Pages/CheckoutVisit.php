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
                    ->whereNull('out_date')
                    ->with(['visitor', 'visitor.docType', 'destination'])
                    ->latest('in_date')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID da Visita')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('visitor.id')
                    ->label('ID Visitante')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('visitor.name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('visitor.doc')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('visitor.docType.type')
                    ->label('Tipo')
                    ->sortable(),
                TextColumn::make('destination.name')
                    ->label('Local')
                    ->sortable(),
                TextColumn::make('in_date')
                    ->label('Entrada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('in_date', 'desc')
            ->actions([
                \Filament\Tables\Actions\Action::make('checkout')
                    ->label('Registrar Saída')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->action(function (\App\Models\VisitorLog $record) {
                        if (!$record->out_date) {
                            $record->update(['out_date' => now()]);
                            
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
            ->bulkActions([])
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
} 