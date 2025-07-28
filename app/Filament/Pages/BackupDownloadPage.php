<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Illuminate\Support\Collection;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Filament\Tables\Concerns\InteractsWithTable;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

/**
 * Página para download de backups
 * 
 * Esta página permite aos usuários com permissão visualizar e baixar 
 * os arquivos de backup existentes no sistema.
 */
class BackupDownloadPage extends Page
{
    use HasPageShield;
    
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Download de Backups';
    protected static ?string $title = 'Download de Backups';
    protected static ?string $navigationGroup = 'Backup';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.backup-download-page';

    // Lista de backups
    public $backups = [];

    public function mount(): void
    {
        $this->loadBackups();
    }

    protected function loadBackups(): void
    {
        $files = [];
        $backupDisk = Storage::disk('backups');
        
        // O nome da aplicação determina o subdiretório onde os backups são salvos
        $appName = env('APP_NAME', 'backup');
        
        // Lista todos os arquivos no subdiretório da aplicação
        $backupFiles = $backupDisk->files($appName);
        
        foreach ($backupFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                $files[] = [
                    'id' => $file, // Usamos o caminho como ID
                    'name' => basename($file),
                    'path' => $file,
                    'size' => $backupDisk->size($file),
                    'date' => $backupDisk->lastModified($file),
                ];
            }
        }
        
        // Ordena por data (mais recente primeiro)
        usort($files, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });
        
        $this->backups = $files;
    }

    protected function getViewData(): array
    {
        return [
            'backups' => $this->backups,
        ];
    }

    public function download($path)
    {
        // Verifica se o arquivo existe
        if (!Storage::disk('backups')->exists($path)) {
            Notification::make()
                ->title('Arquivo não encontrado')
                ->danger()
                ->send();
            
            return;
        }

        // Redireciona para a rota de download
        return redirect()->route('backup.download', ['filename' => $path]);
    }

    public function delete($path)
    {
        // Verifica se o arquivo existe
        if (Storage::disk('backups')->exists($path)) {
            Storage::disk('backups')->delete($path);
            
            Notification::make()
                ->title('Backup excluído com sucesso!')
                ->success()
                ->send();
            
            // Recarrega a lista de backups
            $this->loadBackups();
        } else {
            Notification::make()
                ->title('Arquivo não encontrado')
                ->danger()
                ->send();
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
} 