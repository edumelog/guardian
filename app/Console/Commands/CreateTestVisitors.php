<?php

namespace App\Console\Commands;

use App\Models\Visitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class CreateTestVisitors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visitors:create-test {count=10 : Número de visitantes a serem criados}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria visitantes de teste para fins de performance e resiliência';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Obtém a quantidade de visitantes a serem criados
        $count = (int) $this->argument('count');
        
        if ($count <= 0) {
            $this->error('O número de visitantes deve ser maior que zero.');
            return 1;
        }
        
        // Autentica um usuário para o salvamento do visitor_log
        // (precisamos de um usuário autenticado para o operator_id)
        $userId = 1; // Assumindo que o ID 1 é um usuário administrador válido
        Auth::loginUsingId($userId);
        
        $this->info("Iniciando a criação de {$count} visitantes de teste...");
        $this->newLine();
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        $successCount = 0;
        $errors = [];
        
        // Cria os visitantes usando a factory
        for ($i = 0; $i < $count; $i++) {
            try {
                $visitor = Visitor::factory()->create();
                $successCount++;
                $bar->advance();
            } catch (\Exception $e) {
                $errors[] = "Erro ao criar visitante #{$i}: " . $e->getMessage();
                $bar->advance();
            }
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Exibe o resumo
        $this->info("✅ {$successCount} visitantes criados com sucesso!");
        
        if (count($errors) > 0) {
            $this->error("❌ " . count($errors) . " erros encontrados:");
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
            return 1;
        }
        
        return 0;
    }
}
