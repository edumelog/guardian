<?php

namespace App\Filament\Resources\QZCertificateResource\Pages;

use App\Filament\Resources\QZCertificateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQZCertificate extends EditRecord
{
    protected static string $resource = QZCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
} 