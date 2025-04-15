<?php

namespace App\Filament\Widgets;

use App\Models\Destination;
use App\Models\VisitorLog;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ActiveVisitorStats extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $pollingInterval = '15s';

    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        // Total de visitantes ativos (sem registro de saída)
        $totalActive = VisitorLog::whereNull('out_date')->count();
        
        // Visitantes que entraram hoje
        $todayVisitors = VisitorLog::whereDate('in_date', Carbon::today())->count();
        
        // Destino com mais visitantes ativos
        $topDestination = VisitorLog::whereNull('out_date')
            ->selectRaw('destination_id, count(*) as total')
            ->groupBy('destination_id')
            ->orderByDesc('total')
            ->first();
            
        $topDestinationName = "Nenhum";
        $topDestinationCount = 0;
        
        if ($topDestination) {
            $destination = Destination::find($topDestination->destination_id);
            $topDestinationName = $destination ? $destination->name : "Destino ID: {$topDestination->destination_id}";
            $topDestinationCount = $topDestination->total;
        }

        return [
            Stat::make('Total de Visitantes Ativos', $totalActive)
                ->description('Visitantes sem registro de saída')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
            
            Stat::make('Visitas de Hoje', $todayVisitors)
                ->description('Registros do dia ' . Carbon::today()->format('d/m/Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),
            
            Stat::make('Destino Mais Visitado', $topDestinationName)
                ->description("{$topDestinationCount} visitantes ativos")
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('warning'),
        ];
    }
} 