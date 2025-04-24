<?php

namespace Database\Seeders;

use App\Models\Visitor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TestVisitorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Este seeder cria visitantes de teste para fins de avaliação de desempenho
     * e resiliência. Não deve ser usado em produção e não está registrado no
     * DatabaseSeeder principal.
     * 
     * Uso: php artisan db:seed --class=TestVisitorSeeder
     * ou php artisan db:seed --class=TestVisitorSeeder --count=20
     */
    public function run(): void
    {
        // Verifica se um número específico de visitantes foi solicitado
        $count = $this->command->option('count') ?? 10;
        $count = (int) $count;
        
        if ($count <= 0) {
            $this->command->error('O número de visitantes deve ser maior que zero.');
            return;
        }
        
        // Autentica um usuário para o salvamento do visitor_log
        // (precisamos de um usuário autenticado para o operator_id)
        $userId = 1; // Assumindo que o ID 1 é um usuário administrador válido
        Auth::loginUsingId($userId);
        
        $this->command->info("Iniciando a criação de {$count} visitantes de teste...");
        
        $successCount = 0;
        $errorCount = 0;
        
        // Cria os visitantes usando a factory
        for ($i = 0; $i < $count; $i++) {
            try {
                $visitor = Visitor::factory()->create();
                $successCount++;
                
                // Exibe progresso a cada 5 visitantes
                if ($successCount % 5 === 0 || $successCount === $count) {
                    $this->command->info("Progresso: {$successCount}/{$count} visitantes criados.");
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Erro ao criar visitante de teste #{$i}: " . $e->getMessage());
                $this->command->error("Erro ao criar visitante #{$i}: " . $e->getMessage());
            }
        }
        
        // Exibe o resumo
        $this->command->newLine();
        $this->command->info("✅ {$successCount} visitantes criados com sucesso!");
        
        if ($errorCount > 0) {
            $this->command->error("❌ {$errorCount} erros encontrados. Verifique os logs para mais detalhes.");
        }
    }
}
