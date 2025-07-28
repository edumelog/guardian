<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeder para criar diretórios de storage necessários
 * 
 * Este seeder cria os diretórios de storage que são necessários
 * para o funcionamento do sistema e para o backup.
 */
class StorageDirectoriesSeeder extends Seeder
{
    /**
     * Executa a criação dos diretórios de storage
     */
    public function run(): void
    {
        // Lista de diretórios que devem ser criados
        $directories = [
            'storage/app/public/week-days',
            'storage/app/public/templates',
            'storage/app/private/visitors-photos',
            'storage/app/backups',
            'storage/app/backup-temp',
        ];
        
        foreach ($directories as $directory) {
            $fullPath = base_path($directory);
            
            if (!File::exists($fullPath)) {
                File::makeDirectory($fullPath, 0755, true);
                $this->command->info("Diretório criado: {$directory}");
            } else {
                $this->command->info("Diretório já existe: {$directory}");
            }
        }
        
        // Ajusta permissões para garantir que o usuário www-data seja o proprietário
        // (necessário para a aplicação web executar o backup)
        foreach ($directories as $directory) {
            $fullPath = base_path($directory);
            if (File::exists($fullPath)) {
                // Define o proprietário como www-data:www-data
                system("sudo chown -R www-data:www-data {$fullPath} 2>/dev/null");
                // Ajusta permissões para 775 (leitura/escrita/execução para proprietário e grupo, leitura/execução para outros)
                system("sudo chmod -R 775 {$fullPath} 2>/dev/null");
            }
        }
        
        // Permissões especiais para diretórios sensíveis
        $privateDirectories = [
            'storage/app/private/visitors-photos',
        ];
        
        foreach ($privateDirectories as $directory) {
            $fullPath = base_path($directory);
            if (File::exists($fullPath)) {
                // Permissões mais restritivas para dados sensíveis (750)
                system("sudo chmod -R 750 {$fullPath} 2>/dev/null");
            }
        }
        
        $this->command->info('Diretórios de storage verificados e criados com sucesso!');
    }
} 