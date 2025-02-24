<?php

namespace App\Filament\Resources\QZCertificateResource\Pages;

use App\Filament\Resources\QZCertificateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQZCertificate extends CreateRecord
{
    protected static string $resource = QZCertificateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
} 