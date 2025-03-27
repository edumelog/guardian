<?php

namespace App\Filament\Resources\VisitorRestrictionResource\Pages;

use Filament\Actions;
use Filament\Support\Enums\MaxWidth;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\VisitorRestrictionResource;

class ListVisitorRestrictions extends ListRecords
{
    protected static string $resource = VisitorRestrictionResource::class;

    // Set page width to full
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Criar Restrição')
                ->url(fn (): string => VisitorRestrictionResource::getUrl('create')),
        ];
    }
}
