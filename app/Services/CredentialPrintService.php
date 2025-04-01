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
            'printerConfig' => $printerConfig
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
                'visitor-destination-alias' => $visitor->destination->getFirstAvailableAlias(),
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

            // Get the width and height of the html in pixels
            $htmlWidth_px = $this->getHtmlWidth($html);
            $htmlHeight_px = $this->getHtmlHeight($html);
            Log::info('Dimensões do HTML:', [
                'html_width_px' => $htmlWidth_px,
                'html_height_px' => $htmlHeight_px
            ]);

            // Get all data from printerConfig stored at printerConfig['printOptions']
            $printOptions = $printerConfig['printOptions'] ?? [];
            $resolution = $printerConfig['dpi'] ?? 96;

            // Get the width and height of the paper size in mm
            $paperWidth_mm = $printerConfig['printOptions']['pageWidth'];
            $paperHeight_mm = $printerConfig['printOptions']['pageHeight'];
            Log::info('Dimensões da folha:', [
                'paperWidth_mm' => $paperWidth_mm,
                'paperHeight_mm' => $paperHeight_mm
            ]);

            // Get the width and height of the paper size from mm to px
            $paperWidth_px = $this->convertToPoints($paperWidth_mm, $resolution);
            $paperHeight_px = $this->convertToPoints($paperHeight_mm, $resolution);

            // Obtém a orientação diretamente da raiz do printerConfig
            $orientation = $printerConfig['orientation'] ?? 'portrait';
            
            // Obtém e converte a rotação para número, garantindo que seja 0 se não for válido
            // $rotation = isset($printerConfig['rotation']) ? 
            //     (is_numeric($printerConfig['rotation']) ? (float)$printerConfig['rotation'] : 0) : 
            //     0;

            Log::info('Configurações de impressão:', [
                'orientation' => $orientation,
                // 'rotation' => $rotation,
                'margins' => $printOptions['margins'] ?? [
                    'top' => 0,
                    'right' => 0,
                    'bottom' => 0,
                    'left' => 0
                ],
                'print_options' => $printOptions,
                'printer_config' => $printerConfig
            ]);
            
            // Calculate the scale factor based on the paper size and orientation.
            $scaleFactor = 1;   
            if ($orientation === 'portrait') {
                $scaleFactor = $paperWidth_px / $htmlWidth_px;
            } else {
                $scaleFactor = $paperWidth_px / $htmlHeight_px*.98;
            }
            Log::info('Fator de escala:', [
                'scale_factor' => $scaleFactor
            ]);
            

            // Obtém as configurações de impressão
            $printOptions = $printerConfig['printOptions'] ?? [];
            $margins_mm = $printOptions['margins'] ?? [
                'top' => 0,
                'right' => 0,
                'bottom' => 0,
                'left' => 0
            ];

            $margins_px = [
                'top' => $this->convertToPoints($margins_mm['top'], $resolution)*$scaleFactor,
                'right' => $this->convertToPoints($margins_mm['right'], $resolution)*$scaleFactor,
                'bottom' => $this->convertToPoints($margins_mm['bottom'], $resolution)*$scaleFactor,
                'left' => $this->convertToPoints($margins_mm['left'], $resolution)*$scaleFactor
            ];
            Log::info('Margens em pixels:', [
                'margins_px' => $margins_px,
                'margins_mm' => $margins_mm
            ]);

            try {
                
                $pdf = Browsershot::html($html)
                    ->setNodeBinary('/usr/bin/node')
                    ->setChromePath('/home/admin/.cache/puppeteer/chrome-headless-shell/linux-134.0.6998.35/chrome-headless-shell-linux64/chrome-headless-shell')
                    // ->paperSize($htmlWidth_px, $htmlHeight_px, 'px')
                    ->paperSize($paperWidth_mm, $paperHeight_mm, 'mm')
                    ->margins($margins_px['top'], $margins_px['right'], $margins_px['bottom'], $margins_px['left'], 'px')
                    ->showBackground()
                    ->scale($scaleFactor)
                    // ->scale(1)
                    ->noSandbox()
                    ->deviceScaleFactor(3)
                    ->dismissDialogs()
                    ->waitUntilNetworkIdle()
                    // define the orientation according to orientation in printerConfig when using paperSize mm
                    ->landscape($orientation == 'landscape' || $orientation == 'reverse-landscape' ? true : false)
                    // define the orientation according to orientation in printerConfig when using paperSize px
                    // ->landscape($htmlWidth_px > $htmlHeight_px ? false : true)
                    ->base64pdf();
                    // save at public folder
                    // ->savePdf(public_path('teste_'.$orientation.'.pdf'));
                    // dd("parei aqui");

                Log::info('PDF gerado com sucesso', [
                    'pdf_size' => strlen($pdf)
                ]);

                // Retorna o PDF em base64 e as configurações de impressão para o QZ-Tray
                $returnConfig = [
                    'pdf_base64' => $pdf,
                    'print_config' => [
                        'printer' => $printerConfig['printer'] ?? '',
                        'options' => [
                            // 'margins' => $margins_mm,
                            'orientation' => $orientation,
                            // 'rotation' => $rotation, 
                            'scaleContent' => true,
                            'rasterize' => true,
                            'interpolation' => 'bicubic',
                            'density' => 'best',
                            'altFontRendering' => true,
                            'ignoreTransparency' => true,
                            'colorType' => $printerConfig['printOptions']['colorType'] ?? 'grayscale'
                        ]
                    ]
                ];

                // Adiciona as dimensões ao retorno se estiverem disponíveis
                if (isset($printerConfig['printOptions']) && 
                    isset($printerConfig['printOptions']['pageWidth']) && 
                    isset($printerConfig['printOptions']['pageHeight'])) {
                    $returnConfig['print_config']['options']['size'] = [
                        'width' => $printerConfig['printOptions']['pageWidth'],
                        'height' => $printerConfig['printOptions']['pageHeight']
                    ];
                }

                Log::info('Configuração final retornada:', [
                    'return_config' => $returnConfig['print_config']
                ]);

                return $returnConfig;
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
     * Converte milímetros para pixels baseado no valor de DPI fornecido
     * Fórmula: pixels = (mm * dpi) / 25.4
     * 
     * @param float $value Valor em milímetros
     * @param int $dpi Resolução em DPI (dots per inch)
     * @return float Valor em pixels
     */
    private function convertToPoints($value, $dpi = 96)
    {
        // Conversão de mm para pixels baseada no DPI
        $result = $value * $dpi / 25.4;

        Log::info('Conversão mm para pixels:', [
            'valor_mm' => $value,
            'dpi' => $dpi,
            'valor_px' => $result
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

    /**
     * Extrai a largura em pixels do HTML através do marcador tpl-size
     *
     * @param string $html O conteúdo HTML do template
     * @return int|null A largura em pixels ou null se não encontrada
     */
    private function getHtmlWidth(string $html): ?int
    {
        // Carrega o HTML em um objeto DOMDocument
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        
        // Procura por elementos com a classe 'tpl-size'
        $elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' tpl-size ')]");
        
        if ($elements->length > 0) {
            $element = $elements->item(0);
            
            // Verifica se é um elemento e se tem atributo width
            if ($element instanceof \DOMElement) {
                if ($element->hasAttribute('width')) {
                    $width = (int)$element->getAttribute('width');
                    Log::info('Dimensão de largura encontrada no atributo width com classe tpl-size', [
                        'width' => $width,
                        'element' => $element->nodeName
                    ]);
                    return $width;
                }
                
                // Se não tem atributo width, tenta obter do style
                if ($element->hasAttribute('style')) {
                    $style = $element->getAttribute('style');
                    
                    // Expressão regular corrigida para capturar exatamente a propriedade width
                    // Busca por: (^|;|\s)width\s*:\s*(\d+)px - para garantir que seja a propriedade "width" e não qualquer substring que contenha "width"
                    if (preg_match('/(^|;|\s)width\s*:\s*(\d+)px/i', $style, $matches)) {
                        $width = (int)$matches[2]; // Índice alterado de 1 para 2 devido ao novo grupo de captura
                        Log::info('Dimensão de largura encontrada no style do elemento com classe tpl-size', [
                            'width' => $width,
                            'element' => $element->nodeName,
                            'style' => $style
                        ]);
                        return $width;
                    }
                    
                    // Expressão regular alternativa para buscar width sem a unidade px
                    if (preg_match('/(^|;|\s)width\s*:\s*(\d+)\s*;/i', $style, $matches)) {
                        $width = (int)$matches[2]; // Índice alterado de 1 para 2 devido ao novo grupo de captura
                        Log::info('Dimensão de largura encontrada no style (sem px) do elemento com classe tpl-size', [
                            'width' => $width,
                            'element' => $element->nodeName,
                            'style' => $style
                        ]);
                        return $width;
                    }
                    
                    // Procura o width mesmo que esteja no meio do estilo ou no final sem ponto-e-vírgula
                    if (preg_match('/(^|;|\s)width\s*:\s*(\d+)(px)?(\s*[;$]|\s*$)/i', $style, $matches)) {
                        $width = (int)$matches[2]; // Índice alterado de 1 para 2 devido ao novo grupo de captura
                        Log::info('Dimensão de largura encontrada no pattern completo do elemento com classe tpl-size', [
                            'width' => $width,
                            'element' => $element->nodeName,
                            'style' => $style
                        ]);
                        return $width;
                    }
                }
            }
        }
        
        // Métodos de fallback (mantém os existentes para compatibilidade)
        if (preg_match('/tpl-size="([0-9]+)x([0-9]+)"/', $html, $matches)) {
            Log::info('Dimensões encontradas no marcador tpl-size', [
                'width' => (int)$matches[1],
                'height' => (int)$matches[2]
            ]);
            
            return (int)$matches[1];
        }
        
        if (preg_match('/<meta\s+name="tpl-dimensions"\s+content="([0-9]+)x([0-9]+)"\s*\/?>/i', $html, $matches)) {
            Log::info('Dimensões encontradas na meta tag', [
                'width' => (int)$matches[1],
                'height' => (int)$matches[2]
            ]);
            
            return (int)$matches[1];
        }
        
        Log::warning('Não foi possível encontrar dimensões de largura no HTML');
        return null;
    }

    /**
     * Extrai a altura em pixels do HTML através do marcador tpl-size
     *
     * @param string $html O conteúdo HTML do template
     * @return int|null A altura em pixels ou null se não encontrada
     */
    private function getHtmlHeight(string $html): ?int
    {
        // Carrega o HTML em um objeto DOMDocument
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        
        // Procura por elementos com a classe 'tpl-size'
        $elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' tpl-size ')]");
        
        if ($elements->length > 0) {
            $element = $elements->item(0);
            
            // Verifica se é um elemento e se tem atributo height
            if ($element instanceof \DOMElement) {
                if ($element->hasAttribute('height')) {
                    $height = (int)$element->getAttribute('height');
                    Log::info('Dimensão de altura encontrada no atributo height com classe tpl-size', [
                        'height' => $height,
                        'element' => $element->nodeName
                    ]);
                    return $height;
                }
                
                // Se não tem atributo height, tenta obter do style
                if ($element->hasAttribute('style')) {
                    $style = $element->getAttribute('style');
                    
                    // Expressão regular para capturar exatamente a propriedade height
                    if (preg_match('/(^|;|\s)height\s*:\s*(\d+)px/i', $style, $matches)) {
                        $height = (int)$matches[2]; // Índice alterado de 1 para 2 devido ao novo grupo de captura
                        Log::info('Dimensão de altura encontrada no style do elemento com classe tpl-size', [
                            'height' => $height,
                            'element' => $element->nodeName,
                            'style' => $style
                        ]);
                        return $height;
                    }
                    
                    // Expressão regular alternativa para buscar height sem a unidade px
                    if (preg_match('/(^|;|\s)height\s*:\s*(\d+)\s*;/i', $style, $matches)) {
                        $height = (int)$matches[2]; // Índice alterado de 1 para 2 devido ao novo grupo de captura
                        Log::info('Dimensão de altura encontrada no style (sem px) do elemento com classe tpl-size', [
                            'height' => $height,
                            'element' => $element->nodeName,
                            'style' => $style
                        ]);
                        return $height;
                    }
                    
                    // Procura o height mesmo que esteja no meio do estilo ou no final sem ponto-e-vírgula
                    if (preg_match('/(^|;|\s)height\s*:\s*(\d+)(px)?(\s*[;$]|\s*$)/i', $style, $matches)) {
                        $height = (int)$matches[2]; // Índice alterado de 1 para 2 devido ao novo grupo de captura
                        Log::info('Dimensão de altura encontrada no pattern completo do elemento com classe tpl-size', [
                            'height' => $height,
                            'element' => $element->nodeName,
                            'style' => $style
                        ]);
                        return $height;
                    }
                }
            }
        }
        
        // Métodos de fallback (mantém os existentes para compatibilidade)
        if (preg_match('/tpl-size="([0-9]+)x([0-9]+)"/', $html, $matches)) {
            return (int)$matches[2];
        }
        
        if (preg_match('/<meta\s+name="tpl-dimensions"\s+content="([0-9]+)x([0-9]+)"\s*\/?>/i', $html, $matches)) {
            return (int)$matches[2];
        }
        
        Log::warning('Não foi possível encontrar dimensões de altura no HTML');
        return null;
    }
} 