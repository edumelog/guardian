<?php

namespace Database\Seeders;

use App\Models\WeekDay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para popular a tabela de dias da semana
 * 
 * Este seeder cria os 7 dias da semana com valores padrão para
 * utilização no sistema.
 */
class WeekDaySeeder extends Seeder
{
    /**
     * Executa a criação dos dias da semana
     */
    public function run(): void
    {
        // Limpa registros existentes
        DB::table('week_days')->truncate();
        
        // Lista de dias da semana padrão
        $weekDays = [
            [
                'day_number' => 0,
                'text_value' => 'DOMINGO',
                'is_active' => true,
            ],
            [
                'day_number' => 1,
                'text_value' => 'SEGUNDA',
                'is_active' => true,
            ],
            [
                'day_number' => 2,
                'text_value' => 'TERÇA',
                'is_active' => true,
            ],
            [
                'day_number' => 3,
                'text_value' => 'QUARTA',
                'is_active' => true,
            ],
            [
                'day_number' => 4,
                'text_value' => 'QUINTA',
                'is_active' => true,
            ],
            [
                'day_number' => 5,
                'text_value' => 'SEXTA',
                'is_active' => true,
            ],
            [
                'day_number' => 6,
                'text_value' => 'SÁBADO',
                'is_active' => true,
            ],
        ];
        
        // Cria os registros
        foreach ($weekDays as $day) {
            WeekDay::create($day);
        }
        
        $this->command->info('Dias da semana cadastrados com sucesso!');
    }
}
