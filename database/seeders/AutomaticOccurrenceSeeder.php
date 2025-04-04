<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AutomaticOccurrence;

class AutomaticOccurrenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ocorrência para restrições comuns
        AutomaticOccurrence::updateOrCreate(
            ['key' => 'common_visitor_restriction'],
            [
                'title' => 'Restrição Comum de Visitante',
                'description' => 'Registra automaticamente uma ocorrência quando um visitante com restrição tenta acessar',
                'enabled' => true
            ]
        );
        
        // Ocorrência para restrições preditivas
        AutomaticOccurrence::updateOrCreate(
            ['key' => 'predictive_visitor_restriction'],
            [
                'title' => 'Restrição Preditiva de Visitante',
                'description' => 'Registra automaticamente uma ocorrência quando um visitante corresponde a um padrão de restrição preditiva',
                'enabled' => true
            ]
        );
    }
} 