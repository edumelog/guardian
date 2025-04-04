<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AutomaticOccurrence;

class AutomaticOccurrencesSeeder extends Seeder
{
    public function run(): void
    {
        $occurrences = [

            [
                'key' => 'common_visitor_restriction',
                'title' => 'Restrição de Acesso Comum',
                'description' => 'Registra automaticamente ocorrências quando um visitante com restrição de acesso comum tenta entrar',
                'enabled' => true
            ]
        ];

        foreach ($occurrences as $occurrence) {
            AutomaticOccurrence::updateOrCreate(
                ['key' => $occurrence['key']],
                $occurrence
            );
        }
    }
} 