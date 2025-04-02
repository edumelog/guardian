<?php

namespace App\Filament\Resources\PartialVisitorRestrictionResource\Pages;

use App\Filament\Resources\PartialVisitorRestrictionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartialVisitorRestriction extends EditRecord
{
    protected static string $resource = PartialVisitorRestrictionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
