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
                'title' => 'Restrição de Visitantes Comuns',
                'description' => 'Registro de tentativa de cadastro de visitantes com Restrição de Acesso Comum',
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