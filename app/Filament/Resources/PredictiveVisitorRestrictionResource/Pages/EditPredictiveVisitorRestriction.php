<?php

namespace App\Filament\Resources\PredictiveVisitorRestrictionResource\Pages;

use App\Filament\Resources\PredictiveVisitorRestrictionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPredictiveVisitorRestriction extends EditRecord
{
    protected static string $resource = PredictiveVisitorRestrictionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
