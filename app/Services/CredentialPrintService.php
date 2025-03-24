<?php

namespace App\Services;

use App\Models\Visitor;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;

class CredentialPrintService
{
    /**
     * Gera um preview do PDF da credencial
     *
     * @param Visitor $visitor
     * @param array $printerConfig
     * @return array
     */
    public function generatePreview(Visitor $visitor, array $printerConfig): array
    {
        Log::info('Iniciando geração de preview de credencial', [
            'visitor_id' => $visitor->id,
            'template' => $printerConfig['template'],
            'printer' => $printerConfig['printer']
        ]);

        // Recupera o template
        $templateSlug = pathinfo($printerConfig['template'], PATHINFO_FILENAME);
        Log::info('Processando template', [
            'template_name' => $printerConfig['template'],
            'template_slug' => $templateSlug
        ]);

        // Procura o arquivo index.html no diretório do template
        $templatePath = Storage::disk('public')->path("templates/{$templateSlug}/index.html");
        if (!File::exists($templatePath)) {
            Log::error('Template não encontrado', [
                'template_name' => $printerConfig['template'],
                'template_slug' => $templateSlug,
                'template_path' => $templatePath,
                'template_dir' => Storage::disk('public')->path("templates/{$templateSlug}")
            ]);
            throw new \RuntimeException("Template não encontrado: {$templateSlug}");
        }

        Log::info('Template encontrado', ['path' => $templatePath]);

        // Lê e processa o template
        $template = File::get($templatePath);
        Log::info('Template carregado', ['size' => strlen($template)]);

        // Log do conteúdo do template antes do processamento:
        Log::debug('Conteúdo do template antes do processamento:', ['html' => $template]);

        $processedHtml = $this->processTemplate($template, $visitor);
        Log::info('Template processado', ['size' => strlen($processedHtml)]);

        // Ajusta os caminhos das imagens
        $processedHtml = $this->adjustImagePaths($processedHtml, $templatePath);
        Log::info('Caminhos das imagens ajustados', ['size' => strlen($processedHtml)]);

        // Log do HTML processado para debug
        Log::debug('HTML após processamento:', ['html' => $processedHtml]);

        // Gera um ID único para o arquivo temporário
        $tempId = Str::uuid();
        $tempPath = storage_path("app/temp/previews/{$tempId}.pdf");

        // Certifica que o diretório existe
        if (!File::exists(dirname($tempPath))) {
            Log::info('Criando diretório temporário', ['path' => dirname($tempPath)]);
            File::makeDirectory(dirname($tempPath), 0755, true);
        }

        // Configura o PDF com as dimensões da etiqueta
        Log::info('Configurando dimensões do PDF', [
            'width' => $printerConfig['printOptions']['pageWidth'],
            'height' => $printerConfig['printOptions']['pageHeight'],
            'margins' => $printerConfig['printOptions']['margins'] ?? [],
            'orientation' => $printerConfig['orientation'] ?? 'portrait',
            'dpi' => $printerConfig['dpi'] ?? '96'
        ]);

        // Carrega o HTML no DomPDF
        $pdf = DomPDF::loadHtml($processedHtml);
        
        // Log do diretório atual do DomPDF
        Log::info('Diretório base do DomPDF:', [
            'template_dir' => dirname($templatePath),
            'current_path' => getcwd()
        ]);

        // Configura as dimensões e margens
        $width = $this->convertToPoints($printerConfig['printOptions']['pageWidth'], 'mm');
        $height = $this->convertToPoints($printerConfig['printOptions']['pageHeight'], 'mm');
        
        Log::info('Dimensões convertidas para pontos', [
            'original_width_mm' => $printerConfig['printOptions']['pageWidth'],
            'original_height_mm' => $printerConfig['printOptions']['pageHeight'],
            'width_points' => $width,
            'height_points' => $height
        ]);

        // Configura as margens (em pontos)
        $margins = $printerConfig['printOptions']['margins'] ?? [];
        $marginTop = isset($margins['top']) ? $this->convertToPoints($margins['top'], 'mm') : 0;
        $marginRight = isset($margins['right']) ? $this->convertToPoints($margins['right'], 'mm') : 0;
        $marginBottom = isset($margins['bottom']) ? $this->convertToPoints($margins['bottom'], 'mm') : 0;
        $marginLeft = isset($margins['left']) ? $this->convertToPoints($margins['left'], 'mm') : 0;

        Log::info('Margens convertidas para pontos', [
            'top' => $marginTop,
            'right' => $marginRight,
            'bottom' => $marginBottom,
            'left' => $marginLeft
        ]);

        // Define as margens e outras opções
        $options = [
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => true,
            'dpi' => $printerConfig['dpi'] ?? 96,
            'margin_top' => $marginTop,
            'margin_right' => $marginRight,
            'margin_bottom' => $marginBottom,
            'margin_left' => $marginLeft,
            'defaultPaperSize' => [$width, $height],
            'defaultMediaType' => 'Custom',
            'defaultPageSize' => [$width, $height],
            'chroot' => dirname($templatePath),
            'fontDir' => storage_path('fonts/'),
            'fontCache' => storage_path('fonts/'),
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isFontSubsettingEnabled' => true,
            'enable_css_float' => true,
            'enable_html5_parser' => true,
            'enable_remote' => true,
            'font_height_ratio' => 1.0,
            'adjust_line_height' => true,
            'pdf_page_limit' => 1
        ];

        $pdf->setOptions($options);

        // Ajusta o tamanho do papel para ser exatamente o tamanho configurado
        $pdf->setPaper([
            0,
            0,
            $width,
            $height
        ], $printerConfig['orientation'] ?? 'portrait');

        // Adiciona CSS inline para garantir as dimensões corretas em milímetros
        $processedHtml = str_replace('<body', '<body style="margin:0; padding:0; width:' . $printerConfig['printOptions']['pageWidth'] . 'mm; height:' . $printerConfig['printOptions']['pageHeight'] . 'mm; position:fixed; overflow:hidden;"', $processedHtml);

        // Carrega o HTML com as novas configurações
        $pdf = DomPDF::loadHtml($processedHtml);
        $pdf->setOptions($options);

        // Log das opções do DomPDF
        Log::info('Opções do DomPDF:', [
            'options' => $options,
            'chroot' => dirname($templatePath),
            'template_full_path' => $templatePath,
            'image_path' => dirname($templatePath) . '/img/CMRJ-horizontal.jpg'
        ]);

        // Log das dimensões do papel
        Log::info('Dimensões do papel:', [
            'width' => $width,
            'height' => $height,
            'orientation' => $printerConfig['orientation'] ?? 'portrait'
        ]);

        // Desativa temporariamente o output buffering antes de gerar o PDF
        while (ob_get_level()) ob_end_clean();

        // Salva o PDF temporário
        $pdf->save($tempPath);

        // Verifica se o PDF foi gerado e suas características
        if (File::exists($tempPath)) {
            Log::info('PDF temporário gerado com sucesso', [
                'path' => $tempPath,
                'size' => File::size($tempPath),
                'permissions' => substr(sprintf('%o', fileperms($tempPath)), -4)
            ]);

            // Tenta ler o conteúdo do PDF para verificar se está correto
            $pdfContent = File::get($tempPath);
            Log::info('Conteúdo do PDF verificado', [
                'size' => strlen($pdfContent),
                'contains_image' => strpos($pdfContent, '/Image') !== false
            ]);
        } else {
            Log::error('Falha ao gerar o PDF', ['path' => $tempPath]);
        }

        $previewUrl = URL::temporarySignedRoute(
            'credential.preview.pdf',
            now()->addMinutes(5),
            ['preview' => $tempId]
        );

        Log::info('Preview gerado com sucesso', [
            'temp_id' => $tempId,
            'url_expiration' => now()->addMinutes(5)->toDateTimeString()
        ]);

        // Retorna as informações necessárias
        $response = [
            'preview_url' => $previewUrl,
            'print_config' => [
                'printer' => $printerConfig['printer'],
                'options' => [
                    'size' => [
                        'width' => floatval($printerConfig['printOptions']['pageWidth']),
                        'height' => floatval($printerConfig['printOptions']['pageHeight']),
                        'units' => 'mm'
                    ],
                    'margins' => [
                        'top' => floatval($margins['top'] ?? 0),
                        'right' => floatval($margins['right'] ?? 0),
                        'bottom' => floatval($margins['bottom'] ?? 0),
                        'left' => floatval($margins['left'] ?? 0)
                    ],
                    'orientation' => $printerConfig['orientation'] ?? 'portrait',
                    'units' => 'mm',
                    'dpi' => intval($printerConfig['dpi'] ?? 96),
                    'colorType' => 'blackwhite',
                    'scaleContent' => false,
                    'rasterize' => true,
                    'interpolation' => 'bicubic',
                    'density' => 'best',
                    'altFontRendering' => true,
                    'ignoreTransparency' => true,
                    'fitToPage' => false,
                    'paperSize' => [
                        'width' => floatval($printerConfig['printOptions']['pageWidth']),
                        'height' => floatval($printerConfig['printOptions']['pageHeight']),
                        'units' => 'mm'
                    ],
                    'autoRotate' => false,
                    'forcePageSize' => true,
                    'zoom' => 1.0
                ]
            ]
        ];

        // Desativa temporariamente o output buffering
        while (ob_get_level()) ob_end_clean();

        // Log após limpar o buffer
        Log::info('Retornando resposta JSON', [
            'preview_url' => $response['preview_url'],
            'printer' => $response['print_config']['printer']
        ]);

        return $response;
    }

    /**
     * Processa o template HTML substituindo as marcações tpl-xxx
     *
     * @param string $template
     * @param Visitor $visitor
     * @return string
     */
    private function processTemplate(string $template, Visitor $visitor): string
    {
        Log::info('Iniciando processamento do template', [
            'visitor_id' => $visitor->id,
            'template_size' => strlen($template)
        ]);

        $dom = new DOMDocument();
        @$dom->loadHTML($template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new DOMXPath($dom);
        $elements = $xpath->query("//*[contains(@class, 'tpl-')]");
        
        Log::info('Elementos encontrados para substituição', [
            'count' => $elements->length
        ]);

        $visitorData = $visitor->toArray();
        $replacedFields = [];
        
        foreach ($elements as $element) {
            if (!$element instanceof \DOMElement) continue;
            
            $classes = explode(' ', $element->getAttribute('class'));
            foreach ($classes as $class) {
                if (strpos($class, 'tpl-') === 0) {
                    $field = substr($class, 4);
                    $value = data_get($visitorData, $field);
                    
                    if ($value !== null) {
                        if ($element->nodeName === 'img') {
                            $value = $this->ensureAbsoluteUrl($value);
                            $element->setAttribute('src', $value);
                            $replacedFields[] = "img:{$field}";
                        } else {
                            $element->textContent = $value;
                            $replacedFields[] = $field;
                        }
                    }
                }
            }
        }

        Log::info('Campos substituídos no template', [
            'fields' => $replacedFields
        ]);
        
        $processedHtml = $dom->saveHTML();
        Log::info('Template processado com sucesso', [
            'original_size' => strlen($template),
            'processed_size' => strlen($processedHtml)
        ]);

        return $processedHtml;
    }

    /**
     * Garante que a URL seja absoluta
     *
     * @param string $url
     * @return string
     */
    private function ensureAbsoluteUrl(string $url): string
    {
        if (!Str::startsWith($url, ['http://', 'https://'])) {
            $absoluteUrl = url($url);
            Log::info('URL convertida para absoluta', [
                'original' => $url,
                'absolute' => $absoluteUrl
            ]);
            return $absoluteUrl;
        }
        return $url;
    }

    /**
     * Converte uma medida para pontos (72 pontos = 1 polegada)
     *
     * @param string|float $value
     * @param string $unit
     * @return float
     */
    private function convertToPoints($value, string $unit = 'mm'): float
    {
        // Remove a unidade se estiver na string
        if (is_string($value)) {
            $value = (float) preg_replace('/[^0-9.]/', '', $value);
        }

        // Converte para pontos baseado na unidade
        switch ($unit) {
            case 'mm':
                // 1 mm = 2.83465 pontos
                $points = $value * 2.83465;
                break;
            case 'in':
                // 1 polegada = 72 pontos
                $points = $value * 72;
                break;
            case 'px':
                // 1 pixel = 0.75 pontos (assumindo 96 DPI)
                $points = $value * 0.75;
                break;
            default:
                throw new \InvalidArgumentException("Unidade de medida não suportada: {$unit}");
        }

        Log::info('Convertendo medida para pontos', [
            'value' => $value,
            'unit' => $unit,
            'points' => $points
        ]);

        return $points;
    }

    /**
     * Ajusta os caminhos das imagens no HTML para serem absolutos em relação ao chroot
     *
     * @param string $html
     * @param string $templatePath
     * @return string
     */
    private function adjustImagePaths(string $html, string $templatePath): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            
            // Pula se já for base64 ou URL externa
            if (strpos($src, 'data:image/') === 0 || strpos($src, 'http://') === 0 || strpos($src, 'https://') === 0) {
                continue;
            }

            // Converte o caminho relativo para absoluto em relação ao chroot
            $absolutePath = '/' . ltrim($src, '/');
            
            Log::info('Ajustando caminho da imagem:', [
                'original' => $src,
                'novo' => $absolutePath,
                'arquivo_existe' => File::exists(dirname($templatePath) . '/' . $src)
            ]);

            $img->setAttribute('src', $absolutePath);
        }

        return $dom->saveHTML();
    }
} 