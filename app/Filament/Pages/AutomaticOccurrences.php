<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\AutomaticOccurrence;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class AutomaticOccurrences extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationGroup = 'Análise de Segurança';
    protected static ?string $navigationLabel = 'Ocorrências Automáticas';
    protected static ?string $title = 'Ocorrências Automáticas';
    protected static ?string $slug = 'ocorrencias-automaticas';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.automatic-occurrences';

    public array $occurrences = [];

    public function mount(): void
    {
        // Carrega as ocorrências do banco de dados
        $this->occurrences = AutomaticOccurrence::all()
            ->keyBy('key')
            ->map(function ($occurrence) {
                return [
                    'title' => $occurrence->title,
                    'description' => $occurrence->description,
                    'enabled' => $occurrence->enabled,
                ];
            })
            ->toArray();
    }

    public function toggleOccurrence($key): void
    {
        $occurrence = AutomaticOccurrence::where('key', $key)->first();
        
        if ($occurrence) {
            $occurrence->enabled = !$occurrence->enabled;
            $occurrence->save();

            // Atualiza o array local
            $this->occurrences[$key]['enabled'] = $occurrence->enabled;
            
            // Notifica o usuário
            Notification::make()
                ->title('Configuração atualizada')
                ->success()
                ->send();
        }
    }

    public function getMaxWidthProperty(): MaxWidth
    {
        return MaxWidth::ExtraLarge;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getViewComponents(): array
    {
        return [
            Toggle::class,
        ];
    }
} 