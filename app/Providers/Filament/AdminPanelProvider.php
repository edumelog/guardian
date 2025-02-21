<?php

namespace App\Providers\Filament;

use Filament\Panel\Panel;
use Filament\Panel\PanelProvider;
use Filament\Panel\Color;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Guardian')
            ->brandLogo(asset('images/logo.svg'))
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->login()
            ->profile()
            ->colors([
                'primary' => Color::Amber,
            ]);
    }
} 