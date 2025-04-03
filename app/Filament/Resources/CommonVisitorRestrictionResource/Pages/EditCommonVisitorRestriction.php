<?php

namespace App\Filament\Resources\CommonVisitorRestrictionResource\Pages;

use App\Filament\Resources\CommonVisitorRestrictionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommonVisitorRestriction extends EditRecord
{
    protected static string $resource = CommonVisitorRestrictionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
