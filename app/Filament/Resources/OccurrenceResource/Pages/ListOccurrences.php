<?php

namespace App\Filament\Resources\OccurrenceResource\Pages;

use Filament\Actions;
use Illuminate\Support\Carbon;
use Filament\Support\Enums\MaxWidth;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\OccurrenceResource;
use Filament\Tables\Columns\TextColumn;

class ListOccurrences extends ListRecords
{
    protected static string $resource = OccurrenceResource::class;
    // Max width
    protected ?string $maxContentWidth = MaxWidth::Full->value;
    
    // Por padrão, atualiza a página a cada 1 minuto
    protected $refreshInterval = 60;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nova Ocorrência')
                ->icon('heroicon-o-plus-circle'),
                
            // Actions\ExportAction::make()
            //     ->label('Exportar')
            //     ->icon('heroicon-o-arrow-down-tray')
            //     ->color('success'),
        ];
    }
    
    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }
    
    protected function getDefaultTableSortColumn(): ?string
    {
        return 'occurrence_datetime';
    }
    
    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
    }
}
