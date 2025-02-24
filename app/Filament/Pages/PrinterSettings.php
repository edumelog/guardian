<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
// use Spatie\Permission\Traits\HasRoles;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class PrinterSettings extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'Configurar Impressora';
    protected static ?string $title = 'Configuração da Impressora';
    protected static ?string $slug = 'printer-settings';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.printer-setup';

   

    public function notify(string $status, string $title, string $message): void
    {
        Notification::make()
            ->title($title)
            ->body($message)
            ->status($status)
            ->send();
    }
} 