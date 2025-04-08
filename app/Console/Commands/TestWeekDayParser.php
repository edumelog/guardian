<?php

namespace App\Console\Commands;

use App\Models\WeekDay;
use App\Services\TemplateParserService;
use Illuminate\Console\Command;

class TestWeekDayParser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weekday:test {--template= : Template de teste} {--date= : Data para testar (formato Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa o parser de marcadores de dia da semana';

    /**
     * Execute the console command.
     */
    public function handle(TemplateParserService $parser)
    {
        $this->info('Testando o serviço TemplateParserService');
        $this->newLine();
        
        // Verificar configurações dos dias da semana
        $this->info('Dias da semana cadastrados:');
        $weekdays = WeekDay::orderBy('day_number')->get();
        
        $headers = ['#', 'Dia', 'Texto', 'Imagem', 'Ativo'];
        $rows = [];
        
        foreach ($weekdays as $day) {
            $rows[] = [
                $day->day_number,
                $day->day_name,
                $day->text_value,
                $day->image ? '✓' : '✗',
                $day->is_active ? '✓' : '✗',
            ];
        }
        
        $this->table($headers, $rows);
        $this->newLine();
        
        // Processar data
        $date = $this->option('date') ? now()->createFromFormat('Y-m-d', $this->option('date')) : now();
        if (!$date) {
            $this->error('Formato de data inválido. Use Y-m-d (ex: 2023-04-08)');
            return 1;
        }
        
        $this->info('Data de teste: ' . $date->format('d/m/Y') . ' (' . $date->translatedFormat('l') . ')');
        $this->info('Número do dia da semana: ' . $date->dayOfWeek);
        
        // Buscar dia da semana correspondente
        $weekDay = WeekDay::where('day_number', $date->dayOfWeek)->where('is_active', true)->first();
        if ($weekDay) {
            $this->info('Dia da semana encontrado: ' . $weekDay->day_name);
            $this->info('Texto: ' . $weekDay->formatted_text);
            $this->info('Imagem: ' . ($weekDay->image ? $weekDay->image_url : 'Nenhuma'));
        } else {
            $this->warn('Nenhum registro ativo encontrado para este dia da semana.');
        }
        
        $this->newLine();
        
        // Testar substituição de marcadores
        $templateOpt = $this->option('template');
        $templates = $templateOpt ? [$templateOpt] : [
            'Hoje é {tpl-weekday-txt}',
            'Hoje é {{tpl-weekday-txt}}',
            'Imagem do dia: {tpl-weekday-img}',
            'Texto: {tpl-weekday-txt} / Imagem: {tpl-weekday-img}'
        ];
        
        $this->info('Testes de substituição de marcadores:');
        foreach ($templates as $template) {
            $result = $parser->parseTemplate($template, $date);
            $this->line("Template: <comment>{$template}</comment>");
            $this->line("Resultado: <info>{$result}</info>");
            $this->newLine();
        }
        
        // Mostrar outros métodos
        $this->info('Método getWeekdayText(): ' . $parser->getWeekdayText($date));
        $this->info('Método getWeekdayImageHtml(): ' . $parser->getWeekdayImageHtml($date, ['class' => 'img-fluid']));
        
        return 0;
    }
}
