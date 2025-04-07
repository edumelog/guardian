<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Support\Str;

/**
 * Página para restauração de backups
 * 
 * Esta página permite aos usuários com permissão fazer upload de arquivos de backup
 * e restaurá-los no sistema.
 */
class BackupRestorePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Restaurar Backup';
    protected static ?string $title = 'Restaurar Backup';
    protected static ?string $navigationGroup = 'Backup';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.backup-restore-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Restaurar Backup')
                    ->description('Faça upload de um arquivo de backup para restaurá-lo. Isso substituirá todos os dados atuais!')
                    ->schema([
                        FileUpload::make('backup_file')
                            ->label('Arquivo de Backup')
                            ->required()
                            ->disk('local')
                            ->directory('backup-restore')
                            ->visibility('public')
                            ->maxSize(1024 * 100) // 100 MB
                            ->acceptedFileTypes(['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream'])
                            ->afterStateUpdated(function (string $state) {
                                // Log quando o estado do upload muda para facilitar a depuração
                                \Illuminate\Support\Facades\Log::info('Upload de arquivo atualizado:', [
                                    'state' => $state
                                ]);
                            })
                            ->helperText('Apenas arquivos ZIP gerados pelo sistema de backup são suportados. Máximo: 100MB.')
                    ])
            ])
            ->statePath('data');
    }

    public function restore(): void
    {
        $data = $this->form->getState();
        $backupPath = $data['backup_file'];
        $uploadDir = 'backup-restore';
        
        // Log para verificação
        \Illuminate\Support\Facades\Log::info('Tentando restaurar backup:', [
            'backupPath' => $backupPath,
            'existe' => Storage::disk('local')->exists($backupPath),
            'arquivo_completo' => Storage::disk('local')->path($backupPath)
        ]);

        try {
            // Verificar se o arquivo existe no caminho exato
            if (Storage::disk('local')->exists($backupPath)) {
                $backupFile = Storage::disk('local')->path($backupPath);
                \Illuminate\Support\Facades\Log::info('Arquivo encontrado no caminho exato', ['path' => $backupFile]);
            } else {
                // Se não encontrar no caminho exato, buscar na pasta de upload
                $files = Storage::disk('local')->files($uploadDir);
                \Illuminate\Support\Facades\Log::info('Arquivos no diretório de upload:', ['files' => $files]);
                
                // Buscar qualquer arquivo ZIP no diretório
                $zipFiles = array_filter($files, function($file) {
                    return Str::endsWith($file, '.zip');
                });
                
                if (!empty($zipFiles)) {
                    $backupPath = reset($zipFiles);
                    $backupFile = Storage::disk('local')->path($backupPath);
                    \Illuminate\Support\Facades\Log::info('Encontrado arquivo ZIP no diretório de upload', ['path' => $backupFile]);
                } else {
                    // Verificar em todos os discos locais se não encontrou no diretório de upload
                    $backupFile = null;
                    $localDisks = ['local', 'backups', 'public'];
                    
                    foreach ($localDisks as $disk) {
                        if (array_key_exists($disk, config('filesystems.disks')) && 
                            Storage::disk($disk)->exists($backupPath)) {
                            $backupFile = Storage::disk($disk)->path($backupPath);
                            \Illuminate\Support\Facades\Log::info('Arquivo encontrado no disco', ['disk' => $disk, 'path' => $backupFile]);
                            break;
                        }
                    }
                    
                    if (! $backupFile) {
                        \Illuminate\Support\Facades\Log::error('Backup não encontrado em nenhum disco', [
                            'backupPath' => $backupPath,
                            'discos_verificados' => $localDisks
                        ]);
                        Notification::make()
                            ->title('Erro ao restaurar o backup')
                            ->body('Arquivo de backup não encontrado em nenhum disco.')
                            ->danger()
                            ->send();
                        return;
                    }
                }
            }

            // Coloca o aplicativo em modo de manutenção
            Artisan::call('down');
            
            try {
                // Diretório temporário para extração
                $extractPath = storage_path('app/restore-temp');
                if (!file_exists($extractPath)) {
                    mkdir($extractPath, 0755, true);
                }
    
                // Extrai o arquivo ZIP
                $zip = new ZipArchive;
                if ($zip->open($backupFile) === TRUE) {
                    $zip->extractTo($extractPath);
                    $zip->close();
                    \Illuminate\Support\Facades\Log::info('Arquivo extraído com sucesso', ['path' => $extractPath]);
                } else {
                    throw new \Exception('Não foi possível extrair o arquivo de backup.');
                }
    
                // Verifica se existe um dump de banco de dados
                $dbDumpPath = null;
                $dbDumpsDir = $extractPath . '/db-dumps';
                if (file_exists($dbDumpsDir)) {
                    $dbDumpFiles = scandir($dbDumpsDir);
                    foreach ($dbDumpFiles as $file) {
                        if ($file !== '.' && $file !== '..' && strpos($file, '.sql') !== false) {
                            $dbDumpPath = $dbDumpsDir . '/' . $file;
                            break;
                        }
                    }
                    
                    \Illuminate\Support\Facades\Log::info('Arquivos de banco encontrados:', [
                        'path' => $dbDumpsDir,
                        'files' => $dbDumpFiles ?? [],
                        'selectedFile' => $dbDumpPath
                    ]);
                }
    
                // Restaura o banco de dados se o dump existir
                if ($dbDumpPath) {
                    $dbConfig = config('database.connections.' . config('database.default'));
                    $dbName = $dbConfig['database'];
                    $dbUser = $dbConfig['username'];
                    $dbPassword = $dbConfig['password'];
                    $dbHost = $dbConfig['host'];
    
                    // Executa o comando mysql para restaurar o banco de dados
                    $command = "mysql -h{$dbHost} -u{$dbUser} -p{$dbPassword} {$dbName} < {$dbDumpPath}";
                    \Illuminate\Support\Facades\Log::info('Executando restauração do banco', ['command' => $command]);
                    $result = Process::run($command);
                    
                    if (!$result->successful()) {
                        throw new \Exception('Erro ao restaurar o banco de dados: ' . $result->errorOutput());
                    }
                    
                    \Illuminate\Support\Facades\Log::info('Banco de dados restaurado com sucesso');
                } else {
                    \Illuminate\Support\Facades\Log::warning('Nenhum arquivo de banco de dados encontrado para restauração');
                }
    
                // Restaura os arquivos
                if (file_exists($extractPath . '/files')) {
                    $filesPath = $extractPath . '/files';
                    \Illuminate\Support\Facades\Log::info('Restaurando arquivos', ['path' => $filesPath]);
                    $command = "cp -r {$filesPath}/* " . base_path();
                    $result = Process::run($command);
                    
                    if (!$result->successful()) {
                        throw new \Exception('Erro ao restaurar arquivos: ' . $result->errorOutput());
                    }
                    
                    \Illuminate\Support\Facades\Log::info('Arquivos restaurados com sucesso');
                } else {
                    \Illuminate\Support\Facades\Log::warning('Nenhum diretório de arquivos encontrado para restauração');
                }
    
                // Limpa o cache
                Artisan::call('optimize:clear');
                \Illuminate\Support\Facades\Log::info('Cache limpo com sucesso');
    
                // Remove diretório temporário
                Process::run("rm -rf {$extractPath}");
                \Illuminate\Support\Facades\Log::info('Diretório temporário removido');
    
                // Remove arquivo de backup
                Storage::disk('local')->delete($backupPath);
                \Illuminate\Support\Facades\Log::info('Arquivo de backup removido');
    
                // Notification de sucesso
                Notification::make()
                    ->title('Backup restaurado com sucesso')
                    ->success()
                    ->send();
            } finally {
                // Retira o aplicativo do modo de manutenção (mesmo se ocorrer erro)
                Artisan::call('up');
                \Illuminate\Support\Facades\Log::info('Aplicativo voltou ao modo normal');
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao restaurar backup:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Garante que o aplicativo saia do modo de manutenção mesmo em caso de erro
            if (app()->isDownForMaintenance()) {
                Artisan::call('up');
            }
            
            Notification::make()
                ->title('Erro ao restaurar o backup')
                ->body('Ocorreu um erro: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
} 