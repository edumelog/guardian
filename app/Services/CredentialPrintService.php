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

        $processedHtml = $this->processTemplate($template, $visitor);
        Log::info('Template processado', ['size' => strlen($processedHtml)]);

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
            'height' => $printerConfig['printOptions']['pageHeight']
        ]);

        $pdf = DomPDF::loadHtml($processedHtml);
        $pdf->setPaper([
            0,
            0,
            $this->convertToPoints($printerConfig['printOptions']['pageWidth']),
            $this->convertToPoints($printerConfig['printOptions']['pageHeight'])
        ]);

        // Salva o PDF temporário
        $pdf->save($tempPath);
        Log::info('PDF temporário gerado', [
            'path' => $tempPath,
            'size' => File::size($tempPath)
        ]);

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
        return [
            'preview_url' => $previewUrl,
            'print_config' => [
                'printer' => $printerConfig['printer'],
                'options' => [
                    'pageWidth' => $printerConfig['printOptions']['pageWidth'],
                    'pageHeight' => $printerConfig['printOptions']['pageHeight'],
                    'altFontRendering' => true,
                    'ignoreTransparency' => true
                ]
            ]
        ];
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
     * Converte polegadas para pontos (72 pontos = 1 polegada)
     *
     * @param float $inches
     * @return float
     */
    private function convertToPoints(float $inches): float
    {
        $points = $inches * 72;
        Log::info('Convertendo polegadas para pontos', [
            'inches' => $inches,
            'points' => $points
        ]);
        return $points;
    }
} 