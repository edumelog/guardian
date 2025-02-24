<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class QzTrayDemo extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'QZ Tray Demo';
    protected static ?string $title = 'QZ Tray Demo';
    protected static ?string $slug = 'qz-tray-demo';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.qz-tray-demo';

    
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        return [
            'qzVersion' => '2.2.4' // Versão do QZ Tray
        ];
    }
} 