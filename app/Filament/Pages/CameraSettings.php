<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CameraSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Configurar Câmeras';
    protected static ?string $title = 'Configuração de Câmeras';
    protected static ?string $slug = 'camera-settings';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.camera-setup';

    public function mount(): void
    {
        // Verifica se o usuário tem permissão
        if (!Auth::user()->can('page_CameraSettings')) {
            abort(403);
        }
    }
} 