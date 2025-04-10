<?php

namespace App\Services;

use App\Models\WeekDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Serviço para processamento e substituição de marcadores em templates
 * 
 * Este serviço fornece métodos para processar templates e substituir
 * marcadores relacionados aos dias da semana e outras informações.
 */
class TemplateParserService
{
    /**
     * Substitui todos os marcadores conhecidos em um template
     * 
     * @param string $template O conteúdo do template
     * @param ?Carbon $date Data de referência (usa data atual se não informada)
     * @return string O template com os marcadores substituídos
     */
    public function parseTemplate(string $template, ?Carbon $date = null): string
    {
        // Usa a data atual se não for informada
        $date = $date ?? now();
        
        // Substitui os marcadores de dia da semana
        $template = $this->replaceWeekdayMarkers($template, $date);
        
        // Outros tipos de substituição podem ser adicionados aqui
        
        return $template;
    }
    
    /**
     * Substitui os marcadores de dia da semana em um template
     * 
     * @param string $template O conteúdo do template
     * @param Carbon $date Data de referência
     * @return string O template com os marcadores substituídos
     */
    protected function replaceWeekdayMarkers(string $template, Carbon $date): string
    {
        Log::info('⭐ INÍCIO: Substituindo marcadores de dia da semana', [
            'data' => $date->format('Y-m-d'),
            'dia_semana' => $date->dayOfWeek,
            'nome_dia' => $date->translatedFormat('l'),
            'html_tem_classe_tpl_weekday_img' => (strpos($template, 'tpl-weekday-img') !== false) ? 'SIM' : 'NÃO'
        ]);
        
        // Obtém o registro do dia da semana para a data informada
        $weekDay = WeekDay::getDayByDate($date);
        
        Log::info('⭐ Registro do dia da semana encontrado:', [
            'weekday_encontrado' => ($weekDay) ? 'SIM' : 'NÃO',
            'weekday_id' => $weekDay?->id,
            'weekday_day_number' => $weekDay?->day_number,
            'weekday_text' => $weekDay?->text_value,
            'weekday_tem_imagem' => ($weekDay && $weekDay->image) ? 'SIM' : 'NÃO',
            'weekday_imagem_path' => $weekDay?->image
        ]);
        
        // Se não encontrou um registro para o dia, usa um valor padrão
        if (!$weekDay) {
            $defaultDayName = WeekDay::WEEK_DAYS[$date->dayOfWeek] ?? 'Dia da Semana';
            
            // Substitui apenas o marcador de texto
            $template = str_replace(
                ['{{tpl-weekday-txt}}', '{tpl-weekday-txt}'],
                strtoupper($defaultDayName),
                $template
            );
            
            Log::warning('⭐ Nenhum registro de dia da semana encontrado, usando valor padrão:', [
                'dia_padrao' => $defaultDayName
            ]);
            
            // Para a imagem, mantém o marcador ou usa um placeholder
            return $template;
        }
        
        // Substitui o marcador de texto
        $textoOriginal = $weekDay->formatted_text ?? strtoupper($weekDay->day_name);
        $template = str_replace(
            ['{{tpl-weekday-txt}}', '{tpl-weekday-txt}'],
            $textoOriginal,
            $template
        );
        
        Log::info('⭐ Substituição de texto do dia da semana:', [
            'texto_aplicado' => $textoOriginal
        ]);
        
        // Substitui o marcador de imagem se existir uma imagem
        if ($weekDay->image) {
            Log::info('⭐ Iniciando substituição da imagem do dia da semana');
            
            // 1. Substitui marcadores de texto simples
            $template = str_replace(
                ['{{tpl-weekday-img}}', '{tpl-weekday-img}'],
                $weekDay->image_url,
                $template
            );
            
            // 2. Processa elementos HTML com a classe tpl-weekday-img
            $imageBase64 = $this->getWeekDayImageBase64($weekDay->image);
            
            Log::info('⭐ Informações da imagem base64:', [
                'conseguiu_converter' => ($imageBase64) ? 'SIM' : 'NÃO',
                'tamanho_base64' => ($imageBase64) ? strlen($imageBase64) : 0,
                'primeiros_50_chars' => ($imageBase64) ? substr($imageBase64, 0, 50) . '...' : 'N/A',
            ]);
            
            if ($imageBase64) {
                // Conta quantas ocorrências da classe existem antes da substituição
                $contagem_antes = substr_count($template, 'tpl-weekday-img');
                
                Log::info('⭐ Buscando elementos com classe tpl-weekday-img:', [
                    'ocorrencias_antes' => $contagem_antes
                ]);
                
                // Usa uma expressão regular para encontrar tags <img> ou <div> com a classe tpl-weekday-img
                $template = preg_replace_callback(
                    '/<(img|div)[^>]*class="[^"]*tpl-weekday-img[^"]*"[^>]*>/i',
                    function ($matches) use ($imageBase64) {
                        $tag = $matches[0];
                        $tipoTag = $matches[1]; // img ou div
                        
                        Log::info('⭐ Elemento encontrado para substituição:', [
                            'tipo_tag' => $tipoTag,
                            'tag_original' => $tag,
                            'tem_src' => (strpos($tag, 'src=') !== false) ? 'SIM' : 'NÃO'
                        ]);
                        
                        // Verifica se já tem um src e o substitui, ou adiciona um novo
                        if (strpos($tag, 'src=') !== false) {
                            // Substitui o src existente
                            $tag = preg_replace('/src="[^"]*"/', 'src="' . $imageBase64 . '"', $tag);
                        } else {
                            // Adiciona o atributo src antes do fechamento da tag
                            $tag = str_replace('>', ' src="' . $imageBase64 . '">', $tag);
                        }
                        
                        Log::info('⭐ Elemento após substituição:', [
                            'tag_final' => (strlen($tag) > 100) ? substr($tag, 0, 100) . '...' : $tag
                        ]);
                        
                        return $tag;
                    },
                    $template
                );
                
                // Conta quantas ocorrências da classe existem após a substituição
                $contagem_depois = substr_count($template, 'tpl-weekday-img');
                
                Log::info('⭐ Resultado da substituição de imagens:', [
                    'ocorrencias_antes' => $contagem_antes,
                    'ocorrencias_depois' => $contagem_depois,
                    'substituicoes_feitas' => ($contagem_antes === $contagem_depois) ? 'SIM' : 'NÃO'
                ]);
            }
        } else {
            Log::warning('⭐ O dia da semana não possui imagem cadastrada');
        }
        
        Log::info('⭐ FIM: Substituição de marcadores de dia da semana concluída');
        
        return $template;
    }
    
    /**
     * Obtém a imagem do dia da semana em formato base64
     * 
     * @param string $imagePath Caminho da imagem no sistema de arquivos
     * @return string|null Imagem em formato base64 data-uri ou null se não conseguir ler
     */
    protected function getWeekDayImageBase64(string $imagePath): ?string
    {
        Log::info('🔎 TemplateParserService: Convertendo imagem para base64', [
            'caminho_imagem' => $imagePath
        ]);
        
        try {
            $storagePath = Storage::disk('public')->path($imagePath);
            
            Log::info('🔎 TemplateParserService: Verificando arquivo', [
                'caminho_storage' => $storagePath,
                'arquivo_existe' => file_exists($storagePath) ? 'SIM' : 'NÃO'
            ]);
            
            if (file_exists($storagePath)) {
                $imageData = file_get_contents($storagePath);
                $mime = mime_content_type($storagePath);
                $base64 = 'data:' . $mime . ';base64,' . base64_encode($imageData);
                
                Log::info('🔎 TemplateParserService: Imagem convertida com sucesso', [
                    'mime' => $mime,
                    'tamanho' => strlen($imageData),
                    'base64_tamanho' => strlen($base64),
                    'base64_inicio' => Str::substr($base64, 0, 50) . '... (truncado)'
                ]);
                
                return $base64;
            } else {
                // Verifica se o arquivo existe no caminho direto sem disk
                $alternativePath = public_path('storage/' . $imagePath);
                
                Log::info('🔎 TemplateParserService: Tentando caminho alternativo', [
                    'caminho_alternativo' => $alternativePath,
                    'arquivo_existe' => file_exists($alternativePath) ? 'SIM' : 'NÃO'
                ]);
                
                if (file_exists($alternativePath)) {
                    $imageData = file_get_contents($alternativePath);
                    $mime = mime_content_type($alternativePath);
                    $base64 = 'data:' . $mime . ';base64,' . base64_encode($imageData);
                    
                    Log::info('🔎 TemplateParserService: Imagem convertida pelo caminho alternativo', [
                        'mime' => $mime,
                        'tamanho' => strlen($imageData),
                        'base64_tamanho' => strlen($base64)
                    ]);
                    
                    return $base64;
                }
                
                // Se não encontrou em nenhum lugar, lista os arquivos no diretório de storage
                $storageDir = public_path('storage');
                if (is_dir($storageDir)) {
                    $files = scandir($storageDir);
                    
                    Log::info('🔎 TemplateParserService: Arquivos no diretório de storage', [
                        'caminho' => $storageDir,
                        'arquivos' => array_slice($files, 0, 10) // Lista apenas os primeiros 10 arquivos
                    ]);
                    
                    // Se a imagem contém um path com diretórios, vamos tentar listar o diretório específico
                    if (strpos($imagePath, '/') !== false) {
                        $dirPath = dirname($imagePath);
                        $specificDir = public_path('storage/' . $dirPath);
                        
                        if (is_dir($specificDir)) {
                            $specificFiles = scandir($specificDir);
                            
                            Log::info('🔎 TemplateParserService: Arquivos no diretório específico', [
                                'caminho' => $specificDir,
                                'arquivos' => $specificFiles
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('🔎 TemplateParserService: Erro ao converter imagem para base64', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        Log::warning('🔎 TemplateParserService: Não foi possível converter a imagem para base64');
        return null;
    }
    
    /**
     * Retorna o HTML para exibir a imagem do dia da semana
     * 
     * @param ?Carbon $date Data de referência (usa data atual se não informada)
     * @param array $attributes Atributos HTML adicionais para a tag img
     * @return string Tag img com a imagem do dia ou texto alternativo
     */
    public function getWeekdayImageHtml(?Carbon $date = null, array $attributes = []): string
    {
        $date = $date ?? now();
        $weekDay = WeekDay::getDayByDate($date);
        
        if (!$weekDay || !$weekDay->image) {
            return '';
        }
        
        // Monta os atributos HTML
        $htmlAttrs = '';
        foreach ($attributes as $key => $value) {
            $htmlAttrs .= " {$key}=\"{$value}\"";
        }
        
        return "<img src=\"{$weekDay->image_url}\" alt=\"{$weekDay->day_name}\"{$htmlAttrs}>";
    }
    
    /**
     * Retorna o texto formatado para o dia da semana
     * 
     * @param ?Carbon $date Data de referência (usa data atual se não informada)
     * @return string Texto formatado para o dia da semana
     */
    public function getWeekdayText(?Carbon $date = null): string
    {
        $date = $date ?? now();
        $weekDay = WeekDay::getDayByDate($date);
        
        if (!$weekDay) {
            $defaultDayName = WeekDay::WEEK_DAYS[$date->dayOfWeek] ?? 'Dia da Semana';
            return strtoupper($defaultDayName);
        }
        
        return $weekDay->formatted_text ?? strtoupper($weekDay->day_name);
    }
} 