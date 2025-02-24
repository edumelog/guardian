<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class QzTrayDemo extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'QZ Tray Demo';
    protected static ?string $title = 'QZ Tray Demo';
    protected static ?string $slug = 'qz-tray-demo';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.qz-tray-demo';

    public function mount(): void
    {
        // Verifica se o usuário tem permissão
        if (!Auth::user()->can('page_QzTrayDemo')) {
            abort(403);
        }
    }

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