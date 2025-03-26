<?php

namespace App\Filament\Resources\VisitorRestrictionResource\Pages;

use App\Filament\Resources\VisitorRestrictionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVisitorRestrictions extends ListRecords
{
    protected static string $resource = VisitorRestrictionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Criar Restrição')
                ->url(fn (): string => VisitorRestrictionResource::getUrl('create')),
        ];
    }
}
