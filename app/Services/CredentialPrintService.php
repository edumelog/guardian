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
use Spatie\Browsershot\Browsershot;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\BarcodeGeneratorPNG;

class CredentialPrintService
{
    /**
     * Gera o PDF da credencial para impressão
     *
     * @param Visitor $visitor O visitante para gerar a credencial
     * @param array $printerConfig Configurações da impressora
     * @return array Array com o PDF em base64 e as configurações de impressão
     */
    public function generatePdf(Visitor $visitor, array $printerConfig): array
    {
        Log::info('Iniciando geração de PDF para impressão', [
            'visitor_id' => $visitor->id,
            'visitor_photo' => $visitor->photo,
            'printer_config' => $printerConfig
        ]);

        try {
            // Carrega o template
            $templateName = $printerConfig['template'] ?? 'default';
            // Remove a extensão .zip se existir
            $templateSlug = pathinfo($templateName, PATHINFO_FILENAME);
            $templatePath = Storage::disk('public')->path("templates/{$templateSlug}/index.html");
            
            Log::info('Carregando template', [
                'template_name' => $templateName,
                'template_slug' => $templateSlug,
                'template_path' => $templatePath
            ]);
            
            if (!file_exists($templatePath)) {
                throw new \Exception("Template não encontrado: {$templatePath}");
            }

            // Carrega o HTML do template
            $html = file_get_contents($templatePath);

            // Dados do visitante e relacionamentos
            $photoBase64 = '';
            if ($visitor->photo) {
                $photoBase64 = $this->getPhotoBase64($visitor->photo);
            }

            Log::info('Foto processada:', [
                'photo_attribute' => $visitor->photo,
                'has_base64' => !empty($photoBase64)
            ]);

            $data = [
                'visitor-id' => $visitor->id,
                'visitor-name' => strtoupper($visitor->name),
                'visitor-photo' => $photoBase64,
                'visitor-doc-type' => $visitor->docType->type,
                'visitor-doc' => $visitor->docType->type === 'CPF' 
                    ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $visitor->doc)
                    : $visitor->doc,
                'visitor-destination' => $visitor->destination->name,
                'visitor-destination-alias' => $visitor->destination->alias,
                'visitor-destination-address' => $visitor->destination->address,
                'visitor-destination-phone' => $visitor->destination->phone,
                'visitor-in-datetime' => $visitor->latestLog?->in_date?->format('d/m/Y H:i') ?? '',
                'visitor-out-datetime' => $visitor->latestLog?->out_date?->format('d/m/Y H:i') ?? '',
                'visitor-other' => $visitor->other ?? '',
                'datetime' => now()->format('d/m/Y H:i'),
                'date' => now()->format('d/m/Y'),
                'time' => now()->format('H:i'),
                'visitor-qrcode' => $visitor->latestLog?->id ?? '',
                'visitor-barcode' => $visitor->latestLog?->id ?? ''
            ];

            Log::info('Dados da foto do visitante:', [
                'photo_attribute' => $visitor->photo,
                'has_base64' => !empty($photoBase64)
            ]);

            // Gera os códigos se tivermos um ID de log
            if (!empty($data['visitor-qrcode'])) {
                // Gera o QR Code
                $options = new QROptions([
                    'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                    'eccLevel' => QRCode::ECC_L,
                    'imageBase64' => true,
                    'addQuietzone' => true,
                    'quietzoneSize' => 1,
                    'scale' => 5
                ]);

                $qrcode = new QRCode($options);
                $qrCodeBase64 = $qrcode->render($data['visitor-qrcode']);

                // Gera o código de barras
                $generator = new BarcodeGeneratorPNG();
                $barcodeBase64 = base64_encode($generator->getBarcode(
                    $data['visitor-barcode'],
                    $generator::TYPE_CODE_128,
                    3,
                    100
                ));

                // Atualiza os dados com as imagens em base64
                $data['visitor-qrcode-img'] = $qrCodeBase64;
                $data['visitor-barcode-img'] = 'data:image/png;base64,' . $barcodeBase64;
            }

            // Log dos dados que serão substituídos
            Log::info('Dados para substituição no template:', $data);

            // Processa o template substituindo os marcadores
            $html = $this->processTemplate($html, $data);

            // Configurações do PDF
            $pageWidth = $printerConfig['printOptions']['pageWidth'] ?? 100;
            $pageHeight = $printerConfig['printOptions']['pageHeight'] ?? 65;

            // Converte mm para pixels (96 pixels por polegada)
            $pixelsPerMm = 96 / 25.4;
            $widthPx = ceil($pageWidth * $pixelsPerMm);
            $heightPx = ceil($pageHeight * $pixelsPerMm);

            Log::info('Dimensões do PDF:', [
                'width_mm' => $pageWidth,
                'height_mm' => $pageHeight,
                'width_px' => $widthPx,
                'height_px' => $heightPx
            ]);

            // Gera o PDF usando Browsershot
            Log::info('Iniciando geração do PDF com Browsershot', [
                'width_px' => $widthPx,
                'height_px' => $heightPx
            ]);

            try {
                // Injeta CSS para garantir que o conteúdo fique contido na página
                $styleTag = "<style>
                    html, body {
                        margin: 0 !important;
                        padding: 0 !important;
                        width: {$widthPx}px !important;
                        height: {$heightPx}px !important;
                        max-width: {$widthPx}px !important;
                        max-height: {$heightPx}px !important;
                        overflow: hidden !important;
                    }
                </style>";
                $html = str_replace('</head>', $styleTag . '</head>', $html);

                $pdf = Browsershot::html($html)
                    ->setNodeBinary('/usr/bin/node')
                    ->setChromePath('/home/admin/.cache/puppeteer/chrome-headless-shell/linux-134.0.6998.35/chrome-headless-shell-linux64/chrome-headless-shell')
                    ->paperSize($widthPx, $heightPx, 'px')
                    ->margins(0, 0, 0, 0)
                    ->showBackground()
                    ->landscape(false)
                    ->scale(1)
                    ->noSandbox()
                    ->deviceScaleFactor(2)
                    ->windowSize($widthPx, $heightPx)
                    ->fullPage(false)
                    ->dismissDialogs()
                    ->waitUntilNetworkIdle()
                    ->base64pdf();

                Log::info('PDF gerado com sucesso', [
                    'pdf_size' => strlen($pdf)
                ]);

                // Retorna o PDF em base64 e as configurações de impressão
                return [
                    'pdf_base64' => $pdf,
                    'print_config' => [
                        'printer' => $printerConfig['printer'] ?? '',
                        'options' => [
                            'size' => [
                                'width' => $pageWidth,
                                'height' => $pageHeight
                            ],
                            'margins' => [
                                'top' => 0,
                                'right' => 0,
                                'bottom' => 0,
                                'left' => 0
                            ],
                            'orientation' => 'portrait',
                            'scaleContent' => false,
                            'rasterize' => true,
                            'interpolation' => 'bicubic',
                            'density' => 'best',
                            'altFontRendering' => true,
                            'ignoreTransparency' => true,
                            'colorType' => 'blackwhite'
                        ]
                    ]
                ];
            } catch (\Exception $e) {
                Log::error('Erro ao gerar PDF com Browsershot', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Erro ao gerar PDF', [
                'visitor_id' => $visitor->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Processa o template HTML substituindo os marcadores pelos dados
     *
     * @param string $html O HTML do template
     * @param array $data Array com os dados para substituição
     * @return string O HTML processado
     */
    private function processTemplate(string $html, array $data): string
    {
        // Carrega o HTML em um objeto DOMDocument
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);

        // Procura por elementos com classes que começam com 'tpl-'
        $elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' tpl-')]");

        foreach ($elements as $element) {
            if (!$element instanceof \DOMElement) continue;

            $classes = explode(' ', $element->getAttribute('class'));
            
            // Encontra a classe que começa com 'tpl-'
            $tplClass = collect($classes)->first(fn($class) => str_starts_with($class, 'tpl-'));
            if (!$tplClass) continue;

            // Remove o prefixo 'tpl-' para obter o nome do campo
            $field = substr($tplClass, 4);
            
            Log::info('Processando elemento do template:', [
                'element' => $element->nodeName,
                'class' => $tplClass,
                'field' => $field,
                'has_value' => isset($data[$field]),
                'value' => $data[$field] ?? null
            ]);
            
            // Se não existe valor para o campo, continua
            if (!isset($data[$field])) {
                Log::warning("Campo não encontrado no template: {$field}");
                continue;
            }

            $value = $data[$field];

            // Se é uma imagem, atualiza o src
            if ($element->nodeName === 'img') {
                if ($value) {
                    $element->setAttribute('src', $value);
                    Log::info('Atualizando src da imagem:', [
                        'class' => $tplClass,
                        'old_src' => $element->getAttribute('src'),
                        'new_src' => $value
                    ]);
                }
            }
            // Para outros elementos, atualiza o conteúdo
            else {
                // Remove qualquer conteúdo existente
                while ($element->firstChild) {
                    $element->removeChild($element->firstChild);
                }
                // Adiciona o novo conteúdo
                $element->appendChild($dom->createTextNode($value ?: ''));
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Garante que a URL seja absoluta
     */
    private function ensureAbsoluteUrl(?string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // Se já é uma URL absoluta (http:// ou https://) ou data URL (data:)
        if (preg_match('/^(https?:|data:)/i', $url)) {
            return $url;
        }

        // Se começa com barra, é relativo à raiz
        if (str_starts_with($url, '/')) {
            return config('app.url') . $url;
        }

        // Caso contrário, adiciona a barra e a URL base
        return config('app.url') . '/' . $url;
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

    /**
     * Obtém o conteúdo da foto em base64
     */
    private function getPhotoBase64(string $filename): string
    {
        try {
            $path = Storage::disk('private')->path('visitors-photos/' . $filename);
            
            if (!file_exists($path)) {
                Log::warning('Arquivo de foto não encontrado', ['path' => $path]);
                return '';
            }

            $mime = mime_content_type($path);
            $content = base64_encode(file_get_contents($path));
            
            Log::info('Foto carregada com sucesso', [
                'filename' => $filename,
                'mime' => $mime,
                'size' => strlen($content)
            ]);

            return "data:{$mime};base64,{$content}";
        } catch (\Exception $e) {
            Log::error('Erro ao carregar foto', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }
} 