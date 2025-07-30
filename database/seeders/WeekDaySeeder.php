<?php

namespace Database\Seeders;

use App\Models\WeekDay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Seeder para popular a tabela de dias da semana
 * 
 * Este seeder cria os 7 dias da semana com valores padrão para
 * utilização no sistema e cria o diretório week-days para armazenamento
 * de imagens dos dias da semana.
 * 
 * Utiliza firstOrCreate() para evitar duplicação de dados.
 */
class WeekDaySeeder extends Seeder
{
    /**
     * Executa a criação dos dias da semana
     */
    public function run(): void
    {
        // Cria o diretório week-days se não existir
        $weekDaysPath = storage_path('app/public/week-days');
        if (!File::exists($weekDaysPath)) {
            File::makeDirectory($weekDaysPath, 0755, true);
            $this->command->info('Diretório week-days criado com sucesso!');
        } else {
            $this->command->info('Diretório week-days já existe.');
        }
        
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
        
        // Cria os registros usando firstOrCreate para evitar duplicação
        foreach ($weekDays as $day) {
            WeekDay::firstOrCreate(
                ['day_number' => $day['day_number']], // Chave única para busca
                $day // Dados para criação se não existir
            );
        }
        
        $this->command->info('Dias da semana verificados e cadastrados com sucesso!');
    }
}
