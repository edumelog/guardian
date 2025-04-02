<?php

namespace App\Filament\Widgets;

use App\Models\Destination;
use App\Models\VisitorLog;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\CheckboxList;

class ActiveVisitorsChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Visitantes Ativos por Destino';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?array $destinations = [];
    
    public null|string $filter = null;

    // Array para armazenar as capacidades máximas
    public ?array $destinationCapacities = [];

    // Garantir que o widget seja reativo
    protected function isLive(): bool
    {
        return true;
    }

    public function mount(): void
    {
        parent::mount();
        
        // Carregar os destinos logo na montagem do widget
        $this->loadDestinations();
    }
    
    protected function loadDestinations(): void
    {
        // Carrega destinos ativos com os atributos necessários
        $destinations = Destination::where('is_active', true)
            ->select(['id', 'name', 'max_visitors'])
            ->orderBy('name')
            ->get();
            
        // Cria um array associativo com id => name
        $this->destinations = $destinations->pluck('name', 'id')->toArray();
        
        // Guarda a capacidade máxima em um array separado
        $this->destinationCapacities = $destinations->pluck('max_visitors', 'id')->toArray();
    }

    public function getDescription(): ?string
    {
        return 'Monitoramento de visitantes com visitas em andamento por destino';
    }

    protected function getData(): array
    {
        // Inicializa arrays para dados do gráfico
        $labels = [];
        $datasets = [];
        $counts = [];
        $dataForSorting = [];
        $backgroundColors = [];

        // Se não tiver destinos carregados, tenta carregar novamente
        if (empty($this->destinations)) {
            $this->loadDestinations();
        }

        // Consulta visitantes ativos por destino para todos os destinos (para poder calcular o top 3)
        $allActiveVisitors = VisitorLog::whereNull('out_date')
            ->whereIn('destination_id', array_keys($this->destinations))
            ->select('destination_id', DB::raw('count(*) as total'))
            ->groupBy('destination_id')
            ->get();
            
        // Array para armazenar o Top 3 destinos
        $top3DestinationIds = [];
        
        // Prepara dados para classificação e identificação do Top 3
        $allDestinationsData = [];
        foreach ($allActiveVisitors as $record) {
            $allDestinationsData[] = [
                'id' => $record->destination_id,
                'count' => $record->total
            ];
        }
        
        // Ordena todos os destinos por número de visitantes (decrescente)
        usort($allDestinationsData, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        
        // Obtém os IDs dos 3 destinos mais visitados
        $top3DestinationIds = array_slice(
            array_column($allDestinationsData, 'id'),
            0,
            min(3, count($allDestinationsData))
        );

        // Se tiver filtro aplicado, filtrar os destinos adequadamente
        $destinationsToShow = [];
        if ($this->filter !== null) {
            if ($this->filter === 'todos') {
                $destinationsToShow = array_keys($this->destinations);
            } elseif ($this->filter === 'top3') {
                $destinationsToShow = $top3DestinationIds;
            } else {
                $destinationsToShow = [$this->filter];
            }
        } else {
            $destinationsToShow = array_keys($this->destinations);
        }

        // Filtra os visitantes ativos pelos destinos selecionados
        $filteredVisitors = $allActiveVisitors->filter(function($record) use ($destinationsToShow) {
            return in_array($record->destination_id, $destinationsToShow);
        });

        // Prepara os dados para exibição
        foreach ($filteredVisitors as $record) {
            $destinationId = $record->destination_id;
            $visitorsCount = $record->total;
            $destinationName = $this->destinations[$destinationId] ?? "Destino ID: {$destinationId}";
            $maxVisitors = $this->destinationCapacities[$destinationId] ?? 0;
            
            // Calcula a porcentagem de ocupação (se max_visitors for 0, considera como 0%)
            $occupancyRate = $maxVisitors > 0 ? ($visitorsCount / $maxVisitors) * 100 : 0;
            
            // Define a cor da barra com base na ocupação
            if ($maxVisitors > 0) {
                if ($occupancyRate >= 100) {
                    $color = 'rgba(220, 38, 38, 0.8)'; // Vermelho para 100% ou mais
                } elseif ($occupancyRate >= 75) {
                    $color = 'rgba(234, 88, 12, 0.8)'; // Laranja para 75% ou mais
                } elseif ($occupancyRate >= 50) {
                    $color = 'rgba(234, 179, 8, 0.8)'; // Amarelo para 50% ou mais
                } else {
                    $color = 'rgba(34, 197, 94, 0.8)'; // Verde para < 50%
                }
            } else {
                $color = 'rgba(59, 130, 246, 0.8)'; // Azul para destinos sem limitação
            }
            
            // Adiciona informação de capacidade ao label
            $capacityLabel = $maxVisitors > 0 ? " ({$visitorsCount}/{$maxVisitors})" : " ({$visitorsCount})";
            
            $dataForSorting[] = [
                'name' => $destinationName . $capacityLabel,
                'count' => $visitorsCount,
                'id' => $destinationId,
                'color' => $color,
            ];
        }

        // Ordena os dados pelo número de visitantes (decrescente)
        usort($dataForSorting, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        // Extrai os dados ordenados para os arrays de labels e valores
        foreach ($dataForSorting as $item) {
            $labels[] = $item['name'];
            $counts[] = $item['count'];
            $backgroundColors[] = $item['color'];
        }

        // Adiciona uma entrada para "Nenhum visitante" se não houver dados
        if (empty($labels)) {
            $labels[] = 'Nenhum visitante ativo';
            $counts[] = 0;
            $backgroundColors[] = 'rgba(156, 163, 175, 0.5)'; // Cinza
        }

        // Configura o dataset do gráfico
        $datasets[] = [
            'label' => 'Visitantes Ativos',
            'data' => $counts,
            'backgroundColor' => $backgroundColors,
            'borderColor' => 'rgb(255, 255, 255)',
            'borderWidth' => 1,
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Destinos',
                    ],
                ],
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'stepSize' => 1,
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Número de Visitantes',
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
            'indexAxis' => 'y',
        ];
    }

    // Implementar o sistema de filtros nativo do ChartWidget com valores de IDs corretos
    protected function getFilters(): ?array
    {
        $filters = [
            'todos' => 'Todos os destinos',
            'top3' => 'Top 3 mais visitados'
        ];
        
        // Consulta apenas destinos com visitantes ativos 
        $activeDestinationIds = VisitorLog::whereNull('out_date')
            ->whereIn('destination_id', array_keys($this->destinations))
            ->select('destination_id')
            ->distinct()
            ->pluck('destination_id')
            ->toArray();
            
        // Adiciona apenas os destinos com visitantes ativos à lista de filtros
        foreach ($activeDestinationIds as $destinationId) {
            if (isset($this->destinations[$destinationId])) {
                $filters[$destinationId] = $this->destinations[$destinationId];
            }
        }
        
        return $filters;
    }

    // Configuração de altura dinâmica baseada no número de destinos
    protected function getHeight(): ?string
    {
        $destinationCount = 0;
        
        if ($this->filter !== null && $this->filter !== 'todos') {
            $destinationCount = 1; // Apenas um destino selecionado
        } else {
            $destinationCount = count($this->destinations);
        }
        
        // Altura mínima de 300px ou 50px por destino, o que for maior
        $height = max(300, $destinationCount * 50);
        return "{$height}px";
    }
} 