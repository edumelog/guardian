<?php

namespace App\Filament\Resources\DocTypeResource\Pages;

use App\Filament\Resources\DocTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocTypes extends ListRecords
{
    protected static string $resource = DocTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
} 