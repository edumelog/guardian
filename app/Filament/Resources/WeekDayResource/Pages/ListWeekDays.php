<?php

namespace App\Filament\Resources\WeekDayResource\Pages;

use App\Filament\Resources\WeekDayResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWeekDays extends ListRecords
{
    protected static string $resource = WeekDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
