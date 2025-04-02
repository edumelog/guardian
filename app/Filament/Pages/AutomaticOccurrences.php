<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class AutomaticOccurrences extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationGroup = 'Análise de Segurança';
    protected static ?string $navigationLabel = 'Ocorrências Automáticas';
    protected static ?string $title = 'Ocorrências Automáticas';
    protected static ?string $slug = 'ocorrencias-automaticas';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.automatic-occurrences';

    // Array com as ocorrências automáticas
    public array $occurrences = [
        'doc_expired' => [
            'title' => 'Documento Vencido',
            'description' => 'Bloqueia automaticamente visitantes com documentos vencidos',
            'enabled' => true
        ],
        'multiple_visits' => [
            'title' => 'Múltiplas Visitas Simultâneas',
            'description' => 'Bloqueia tentativas de entrada simultânea do mesmo visitante',
            'enabled' => true
        ],
        'blacklist' => [
            'title' => 'Lista Negra',
            'description' => 'Bloqueia automaticamente visitantes que estão na lista negra',
            'enabled' => true
        ],
        'time_restriction' => [
            'title' => 'Restrição de Horário',
            'description' => 'Bloqueia entrada fora do horário permitido',
            'enabled' => false
        ]
    ];

    public function mount(): void
    {
        // Carrega as configurações salvas, se existirem
        if (Storage::exists('automatic_occurrences.json')) {
            $saved = json_decode(Storage::get('automatic_occurrences.json'), true);
            foreach ($saved as $key => $value) {
                if (isset($this->occurrences[$key])) {
                    $this->occurrences[$key]['enabled'] = $value['enabled'];
                }
            }
        }
    }

    public function toggleOccurrence($key): void
    {
        if (isset($this->occurrences[$key])) {
            $this->occurrences[$key]['enabled'] = !$this->occurrences[$key]['enabled'];
            
            // Salva as configurações
            Storage::put('automatic_occurrences.json', json_encode($this->occurrences));
            
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

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->can('page_AutomaticOccurrences') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getViewData(): array
    {
        return [
            'occurrences' => $this->occurrences
        ];
    }

    protected function getViewComponents(): array
    {
        return [
            Toggle::class,
        ];
    }
} 