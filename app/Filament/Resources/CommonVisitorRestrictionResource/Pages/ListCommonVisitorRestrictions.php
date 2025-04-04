<?php

namespace App\Filament\Resources\CommonVisitorRestrictionResource\Pages;

use App\Filament\Resources\CommonVisitorRestrictionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommonVisitorRestrictions extends ListRecords
{
    protected static string $resource = CommonVisitorRestrictionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
