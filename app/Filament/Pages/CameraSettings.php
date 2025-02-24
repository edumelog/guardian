<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class CameraSettings extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Configurar Câmeras';
    protected static ?string $title = 'Configuração de Câmeras';
    protected static ?string $slug = 'camera-settings';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.camera-setup';


} 