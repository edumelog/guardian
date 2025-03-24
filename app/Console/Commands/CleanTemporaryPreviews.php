<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CleanTemporaryPreviews extends Command
{
    protected $signature = 'credentials:clean-previews';
    protected $description = 'Limpa arquivos temporários de preview mais antigos que 1 hora';

    public function handle()
    {
        $directory = storage_path('app/temp/previews');
        
        Log::info('Iniciando limpeza de previews temporários', [
            'directory' => $directory
        ]);

        if (!File::exists($directory)) {
            Log::warning('Diretório de previews não encontrado', [
                'directory' => $directory
            ]);
            $this->warn('O diretório de previews não existe.');
            return;
        }

        $files = File::files($directory);
        $count = 0;
        $errors = 0;
        $oneHourAgo = now()->subHour()->timestamp;

        Log::info('Verificando arquivos para limpeza', [
            'total_files' => count($files),
            'older_than' => date('Y-m-d H:i:s', $oneHourAgo)
        ]);

        foreach ($files as $file) {
            try {
                if ($file->getMTime() < $oneHourAgo) {
                    File::delete($file->getPathname());
                    $count++;

                    Log::info('Arquivo de preview removido', [
                        'file' => $file->getFilename(),
                        'age' => now()->subSeconds(now()->timestamp - $file->getMTime())->diffForHumans()
                    ]);
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Erro ao remover arquivo de preview', [
                    'file' => $file->getFilename(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Limpeza de previews concluída', [
            'files_removed' => $count,
            'errors' => $errors
        ]);

        $this->info("Foram removidos {$count} arquivo(s) de preview.");
        
        if ($errors > 0) {
            $this->warn("Ocorreram {$errors} erro(s) durante a limpeza.");
        }
    }
} 