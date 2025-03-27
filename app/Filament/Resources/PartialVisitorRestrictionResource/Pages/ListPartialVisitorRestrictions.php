<?php

namespace App\Filament\Resources\PartialVisitorRestrictionResource\Pages;

use App\Filament\Resources\PartialVisitorRestrictionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
class ListPartialVisitorRestrictions extends ListRecords
{
    protected static string $resource = PartialVisitorRestrictionResource::class;
    // Set the page width to full
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Criar Restrição Parcial'),
        ];
    }
}
