<?php

namespace App\Filament\Resources\QZCertificateResource\Pages;

use App\Filament\Resources\QZCertificateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQZCertificates extends ListRecords
{
    protected static string $resource = QZCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
} 