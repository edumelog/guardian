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
    protected static ?int $navigationSort = 3;

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
                // Verifica o diretório private antes da restauração
                $privateDir = storage_path('app/private');
                $checkPrivateCommand = "find {$privateDir} -type d | sort";
                $privateResult = Process::run($checkPrivateCommand);
                
                if ($privateResult->successful()) {
                    \Illuminate\Support\Facades\Log::info('Estrutura do diretório private ANTES da restauração:', [
                        'diretorios' => explode("\n", trim($privateResult->output()))
                    ]);
                    
                    // Verifica se existem fotos de visitantes antes da restauração
                    $visitorsPhotosDir = storage_path('app/private/visitors-photos');
                    if (file_exists($visitorsPhotosDir)) {
                        $fotosCommand = "find {$visitorsPhotosDir} -type f | wc -l";
                        $countResult = Process::run($fotosCommand);
                        \Illuminate\Support\Facades\Log::info('Contagem de fotos ANTES da restauração:', [
                            'numero_arquivos' => trim($countResult->output())
                        ]);
                    } else {
                        \Illuminate\Support\Facades\Log::info('Diretório de fotos não existe antes da restauração');
                    }
                }
                
                // Diretório temporário para extração
                $extractPath = storage_path('app/restore-temp');
                if (!file_exists($extractPath)) {
                    mkdir($extractPath, 0755, true);
                }
    
                // Extrai o arquivo ZIP
                $zip = new ZipArchive;
                if ($zip->open($backupFile) === TRUE) {
                    // Listar o conteúdo do ZIP antes de extrair
                    $zipContents = [];
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        // Verificar se o arquivo parece ser uma foto de visitante
                        if (strpos($filename, 'visitors-photos') !== false) {
                            $zipContents[] = $filename;
                        }
                    }
                    
                    \Illuminate\Support\Facades\Log::info('Conteúdo do ZIP relacionado a fotos de visitantes:', [
                        'total_arquivos' => $zip->numFiles,
                        'arquivos_de_fotos' => $zipContents
                    ]);
                    
                    $zip->extractTo($extractPath);
                    $zip->close();
                    \Illuminate\Support\Facades\Log::info('Arquivo extraído com sucesso', ['path' => $extractPath]);
                    
                    // Listar diretórios após a extração
                    $lsCommand = "find {$extractPath} -type d | sort";
                    $lsResult = Process::run($lsCommand);
                    if ($lsResult->successful()) {
                        \Illuminate\Support\Facades\Log::info('Estrutura de diretórios no backup:', [
                            'diretorios' => explode("\n", trim($lsResult->output()))
                        ]);
                    }
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
    
                // Restaura os arquivos de acordo com a estrutura do backup
                $filesFound = false;
                
                // Verifica se existe o diretório files (formato antigo)
                if (file_exists($extractPath . '/files')) {
                    $filesPath = $extractPath . '/files';
                    \Illuminate\Support\Facades\Log::info('Restaurando arquivos do diretório files/', ['path' => $filesPath]);
                    $filesFound = true;
                } else {
                    // Verifica se existe a estrutura completa de diretórios (formato novo)
                    $varPath = $extractPath . '/var/www/html';
                    if (file_exists($varPath)) {
                        \Illuminate\Support\Facades\Log::info('Estrutura completa de diretórios encontrada', ['path' => $varPath]);
                        
                        // Restaura diretamente as fotos dos visitantes
                        $backupPhotosPath = $extractPath . '/var/www/html/storage/app/private/visitors-photos';
                        $destPhotosPath = storage_path('app/private/visitors-photos');
                        
                        if (file_exists($backupPhotosPath)) {
                            // Garante que o diretório de destino existe
                            if (!file_exists($destPhotosPath)) {
                                mkdir($destPhotosPath, 0755, true);
                            }
                            
                            // Copia as fotos com opção forçada e recursiva
                            $command = "cp -rfv {$backupPhotosPath}/* {$destPhotosPath} 2>&1";
                            \Illuminate\Support\Facades\Log::info('Copiando fotos dos visitantes diretamente:', [
                                'command' => $command,
                                'source' => $backupPhotosPath,
                                'destination' => $destPhotosPath
                            ]);
                            
                            $result = Process::run($command);
                            \Illuminate\Support\Facades\Log::info('Resultado da cópia direta:', [
                                'output' => $result->output(),
                                'exitCode' => $result->exitCode(),
                                'successful' => $result->successful()
                            ]);
                            
                            // Ajusta permissões
                            $chmodCmd = "chmod -R 755 {$destPhotosPath}";
                            Process::run($chmodCmd);
                            
                            $filesFound = true;
                        } else {
                            \Illuminate\Support\Facades\Log::warning('Diretório de fotos não encontrado na estrutura completa', [
                                'expected_path' => $backupPhotosPath
                            ]);
                        }
                    }
                }
                
                if (!$filesFound) {
                    \Illuminate\Support\Facades\Log::warning('Nenhum diretório de arquivos reconhecível encontrado para restauração');
                }
    
                // Limpa o cache
                Artisan::call('optimize:clear');
                \Illuminate\Support\Facades\Log::info('Cache limpo com sucesso');
                
                // Verifica o diretório de fotos APÓS a restauração
                $visitorsPhotosDir = storage_path('app/private/visitors-photos');
                if (file_exists($visitorsPhotosDir)) {
                    $fotosCommand = "find {$visitorsPhotosDir} -type f | wc -l";
                    $countResult = Process::run($fotosCommand);
                    \Illuminate\Support\Facades\Log::info('Contagem de fotos APÓS a restauração:', [
                        'numero_arquivos' => trim($countResult->output())
                    ]);
                    
                    // Lista alguns exemplos de fotos
                    $listCommand = "find {$visitorsPhotosDir} -type f -name '*.jpg' -o -name '*.png' | head -n 10";
                    $listResult = Process::run($listCommand);
                    \Illuminate\Support\Facades\Log::info('Exemplos de fotos restauradas:', [
                        'arquivos' => explode("\n", trim($listResult->output()))
                    ]);
                    
                    // Verifica permissões
                    $permCommand = "ls -la {$visitorsPhotosDir} | head -n 10";
                    $permResult = Process::run($permCommand);
                    \Illuminate\Support\Facades\Log::info('Permissões das fotos:', [
                        'permissoes' => $permResult->output()
                    ]);
                } else {
                    \Illuminate\Support\Facades\Log::error('Diretório de fotos NÃO EXISTE após a restauração!');
                }
    
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