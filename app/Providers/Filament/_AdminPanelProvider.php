<?php

namespace App\Providers\Filament;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Support\ServiceProvider;
use Filament\Panel\Panel;

class AdminPanelProvider extends ServiceProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->bootUsing(function () {
                // Registrar componentes personalizados
                FilamentView::registerComponents([
                    'restriction-alert-modal' => 'components.restriction-alert-modal',
                ]);
            });
    }
} 