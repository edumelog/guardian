<?php

namespace App\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;
use Illuminate\Contracts\Support\Htmlable;

class Backups extends BaseBackups
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    
    protected static ?int $navigationSort = 1;
    
    public static function getNavigationGroup(): ?string
    {
        return 'Backup';
    }
    
    public function getHeading(): string | Htmlable
    {
        return 'Backups do Sistema';
    }
} 