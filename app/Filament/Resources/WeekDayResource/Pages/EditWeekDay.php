<?php

namespace App\Filament\Resources\WeekDayResource\Pages;

use App\Filament\Resources\WeekDayResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWeekDay extends EditRecord
{
    protected static string $resource = WeekDayResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
