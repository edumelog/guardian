<?php

namespace App\Filament\Resources\PartialVisitorRestrictionResource\Pages;

use App\Filament\Resources\PartialVisitorRestrictionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartialVisitorRestrictions extends ListRecords
{
    protected static string $resource = PartialVisitorRestrictionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Criar Restrição Parcial'),
        ];
    }
}
