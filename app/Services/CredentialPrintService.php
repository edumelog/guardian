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
     * Gera o PDF da credencial para impressão
     *
     * @param Visitor $visitor
     * @param array $printerConfig
     * @return array
     */
    public function generatePreview(Visitor $visitor, array $printerConfig): array
    {
        Log::info('Iniciando geração de PDF para impressão', [
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
        $processedHtml = $this->processTemplate($template, $visitor);
        
        // Ajusta os caminhos das imagens
        $processedHtml = $this->adjustImagePaths($processedHtml, $templatePath);

        // Gera um ID único para o arquivo temporário
        $tempId = Str::uuid();
        $tempPath = storage_path("app/private/temp/previews/{$tempId}.pdf");

        // Certifica que o diretório existe
        if (!File::exists(dirname($tempPath))) {
            Log::info('Criando diretório temporário', ['path' => dirname($tempPath)]);
            File::makeDirectory(dirname($tempPath), 0755, true);
        }

        // Configura as dimensões e margens
        $width = $this->convertToPoints($printerConfig['printOptions']['pageWidth']);
        $height = $this->convertToPoints($printerConfig['printOptions']['pageHeight']);
        
        // Configura as margens (em pontos)
        $margins = $printerConfig['printOptions']['margins'] ?? [];
        $marginTop = isset($margins['top']) ? $this->convertToPoints($margins['top']) : 0;
        $marginRight = isset($margins['right']) ? $this->convertToPoints($margins['right']) : 0;
        $marginBottom = isset($margins['bottom']) ? $this->convertToPoints($margins['bottom']) : 0;
        $marginLeft = isset($margins['left']) ? $this->convertToPoints($margins['left']) : 0;

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

        // Carrega o HTML no DomPDF
        $pdf = DomPDF::loadHtml($processedHtml);
        $pdf->setOptions($options);

        // Ajusta o tamanho do papel
        $pdf->setPaper([0, 0, $width, $height], $printerConfig['orientation'] ?? 'portrait');

        // Adiciona CSS inline para garantir as dimensões corretas
        $processedHtml = str_replace('<body', '<body style="margin:0; padding:0; width:' . $printerConfig['printOptions']['pageWidth'] . 'mm; height:' . $printerConfig['printOptions']['pageHeight'] . 'mm; position:fixed; overflow:hidden;"', $processedHtml);

        // Carrega o HTML com as novas configurações
        $pdf = DomPDF::loadHtml($processedHtml);
        $pdf->setOptions($options);

        // Desativa temporariamente o output buffering antes de gerar o PDF
        while (ob_get_level()) ob_end_clean();

        // Salva o PDF temporário
        $pdf->save($tempPath);

        // Verifica se o PDF foi gerado
        if (!File::exists($tempPath)) {
            Log::error('Falha ao gerar o PDF', ['path' => $tempPath]);
            throw new \RuntimeException('Falha ao gerar o PDF temporário');
        }

        // Converte o PDF para base64
        $pdfBase64 = base64_encode(File::get($tempPath));

        // Remove o arquivo temporário imediatamente após a conversão
        File::delete($tempPath);

        // Retorna as informações necessárias para impressão
        $response = [
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
            ],
            'pdf_base64' => $pdfBase64
        ];

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
     * Converte milímetros para pontos (unidade usada pelo DomPDF)
     * 1 mm = 2.835 pontos (72/25.4)
     * 
     * @param float $value Valor em milímetros
     * @return float Valor em pontos
     */
    private function convertToPoints($value)
    {
        // Conversão direta de mm para pontos (72/25.4 ≈ 2.835)
        $result = $value * (72/25.4);

        Log::info('Conversão mm para pontos:', [
            'valor_mm' => $value,
            'valor_pt' => $result
        ]);

        return $result;
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