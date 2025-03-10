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
    
    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';
    protected static ?string $navigationLabel = 'Registro de Saída';
    protected static ?string $title = 'Registro de Saída de Visitantes';
    protected static ?string $slug = 'checkout-visit';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationGroup = 'Controle de Acesso';
    protected static string $view = 'filament.pages.checkout-visit';

    public $quickCheckoutDoc = '';

    public function quickCheckout(): void
    {
        // Padroniza o documento com 16 dígitos
        $formattedDoc = str_pad($this->quickCheckoutDoc, 16, '0', STR_PAD_LEFT);

        // Busca o visitante pelo documento
        $visitor = Visitor::where('doc', $formattedDoc)->first();

        if (!$visitor) {
            // Tenta buscar sem os zeros à esquerda também
            $visitor = Visitor::where('doc', ltrim($formattedDoc, '0'))->first();

            if (!$visitor) {
                Notification::make()
                    ->warning()
                    ->title('Visitante não encontrado')
                    ->body('Nenhum visitante encontrado com este documento.')
                    ->send();
                return;
            }
        }

        // Verifica se há uma visita em andamento
        $lastVisit = $visitor->visitorLogs()
            ->latest('in_date')
            ->first();

        if (!$lastVisit || $lastVisit->out_date !== null) {
            Notification::make()
                ->warning()
                ->title('Sem visita em andamento')
                ->body("Este visitante não possui uma visita em andamento.")
                ->send();
            return;
        }

        // Registra a saída
        $lastVisit->update([
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
                Visitor::query()
                    ->whereHas('visitorLogs', function (Builder $query) {
                        $query->whereNull('out_date');
                    })
                    ->with(['visitorLogs' => function ($query) {
                        $query->whereNull('out_date')
                            ->latest('in_date')
                            ->limit(1);
                    }, 'docType', 'visitorLogs.destination'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('doc')
                    ->label('Documento')
                    ->formatStateUsing(fn (string $state): string => str_pad($state, 16, '0', STR_PAD_LEFT))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('docType.type')
                    ->label('Tipo')
                    ->sortable(),
                TextColumn::make('visitorLogs.0.destination.name')
                    ->label('Local')
                    ->sortable(),
                TextColumn::make('visitorLogs.0.in_date')
                    ->label('Entrada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                \Filament\Tables\Actions\Action::make('checkout')
                    ->label('Registrar Saída')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->action(function (Visitor $record) {
                        $lastVisit = $record->visitorLogs()->latest('in_date')->first();
                        if ($lastVisit && !$lastVisit->out_date) {
                            $lastVisit->update(['out_date' => now()]);
                            
                            Notification::make()
                                ->success()
                                ->title('Saída registrada')
                                ->body("Saída registrada com sucesso para o visitante {$record->name}.")
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