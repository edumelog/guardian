<?php

namespace App\Filament\Pages;

use Illuminate\Contracts\Support\Htmlable;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    use HasPageShield;
    
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