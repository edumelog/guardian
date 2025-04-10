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

            // Get the width and height of the paper size in mm
            $paperWidth_mm = $printerConfig['printOptions']['pageWidth'];
            $paperHeight_mm = $printerConfig['printOptions']['pageHeight'];
            Log::info('Dimensões da folha:', [
                'paperWidth_mm' => $paperWidth_mm,
                'paperHeight_mm' => $paperHeight_mm
            ]);

            // Obtém a orientação diretamente da raiz do printerConfig
            $orientation = $printerConfig['orientation'] ?? 'portrait';
            
            
            // Obtém as configurações de impressão
            $printOptions = $printerConfig['printOptions'] ?? [];
            $margins_mm = $printOptions['margins'] ?? [
                'top' => 0,
                'right' => 0,
                'bottom' => 0,
                'left' => 0
            ];
            
            Log::info('Configurações de impressão:', [
                'orientation' => $orientation,
                'margins' => $margins_mm,
                'printer_config' => $printerConfig
            ]);

            try {
                
                $pdf = Browsershot::html($html)
                    ->setNodeBinary('/usr/bin/node')
                    ->setChromePath('/opt/google/chrome/chrome')
                    ->paperSize($paperWidth_mm, $paperHeight_mm, 'mm')
                    ->margins($margins_mm['top'], $margins_mm['right'], $margins_mm['bottom'], $margins_mm['left'], 'mm')
                    ->showBackground()
                    ->scale(1)
                    ->noSandbox()
                    ->deviceScaleFactor(3)
                    ->dismissDialogs()
                    ->waitUntilNetworkIdle()
                    // define the orientation according to orientation in printerConfig when using paperSize mm
                    ->landscape($orientation == 'landscape' || $orientation == 'reverse-landscape' ? true : false)
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
                            'margins' => $margins_mm,
                            'orientation' => $orientation,
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

        // Processa os marcadores do dia da semana
        $this->processWeekdayElements($dom, $xpath);

        // Procura por elementos com classes que começam com 'tpl-'
        $elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' tpl-')]");

        foreach ($elements as $element) {
            if (!$element instanceof \DOMElement) continue;

            $classes = explode(' ', $element->getAttribute('class'));
            
            // Encontra a classe que começa com 'tpl-'
            $tplClass = collect($classes)->first(fn($class) => str_starts_with($class, 'tpl-'));
            if (!$tplClass) continue;
            
            // Pula o processamento de elementos com classe tpl-weekday-img ou tpl-weekday-txt
            // porque já foram processados na etapa anterior
            if ($tplClass === 'tpl-weekday-img' || $tplClass === 'tpl-weekday-txt') {
                continue;
            }

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
     * Processa elementos com as classes tpl-weekday-img e tpl-weekday-txt
     * 
     * @param \DOMDocument $dom
     * @param \DOMXPath $xpath
     */
    private function processWeekdayElements(\DOMDocument $dom, \DOMXPath $xpath): void
    {
        // Obtém o dia da semana atual (1-7, onde 1 é segunda-feira)
        $weekday = date('N');
        Log::info('Processando elementos do dia da semana:', [
            'weekday' => $weekday,
            'dia_nome' => date('l')
        ]);

        try {
            // Busca o registro do dia da semana no banco
            $weekdayModel = \App\Models\WeekDay::where('day_number', $weekday)->first();
            
            if (!$weekdayModel) {
                Log::warning('Dia da semana não encontrado no banco de dados:', [
                    'weekday' => $weekday,
                    'dia_nome' => date('l')
                ]);
                return;
            }
            
            // Processa os elementos de texto do dia da semana
            $this->processWeekdayTextElements($dom, $xpath, $weekdayModel);
            
            // Processa os elementos de imagem do dia da semana (apenas se houver imagem)
            if ($weekdayModel->image) {
                $this->processWeekdayImageElements($dom, $xpath, $weekdayModel);
            } else {
                Log::info('Dia da semana não possui imagem cadastrada:', [
                    'weekday' => $weekday,
                    'dia_nome' => date('l')
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Erro ao processar elementos do dia da semana:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Processa elementos com a classe tpl-weekday-txt, adicionando o texto do dia da semana
     * 
     * @param \DOMDocument $dom
     * @param \DOMXPath $xpath
     * @param \App\Models\WeekDay $weekdayModel
     */
    private function processWeekdayTextElements(\DOMDocument $dom, \DOMXPath $xpath, \App\Models\WeekDay $weekdayModel): void
    {
        // Busca por elementos com a classe tpl-weekday-txt
        $elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' tpl-weekday-txt')]");
        
        if ($elements->length === 0) {
            Log::info('Nenhum elemento com classe tpl-weekday-txt encontrado');
            return;
        }
        
        // Usa texto formatado ou o nome do dia em maiúsculas
        $dayText = $weekdayModel->formatted_text ?? strtoupper($weekdayModel->day_name);
        
        Log::info('Elementos com classe tpl-weekday-txt encontrados:', [
            'total' => $elements->length,
            'texto' => $dayText
        ]);
        
        foreach ($elements as $element) {
            if (!$element instanceof \DOMElement) continue;
            
            // Remove conteúdo existente
            while ($element->firstChild) {
                $element->removeChild($element->firstChild);
            }
            
            // Adiciona o texto do dia da semana
            $element->appendChild($dom->createTextNode($dayText));
        }
    }
    
    /**
     * Processa elementos com a classe tpl-weekday-img, adicionando a imagem do dia da semana
     * 
     * @param \DOMDocument $dom
     * @param \DOMXPath $xpath
     * @param \App\Models\WeekDay $weekdayModel
     */
    private function processWeekdayImageElements(\DOMDocument $dom, \DOMXPath $xpath, \App\Models\WeekDay $weekdayModel): void
    {
        // Busca por elementos com a classe tpl-weekday-img
        $elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' tpl-weekday-img')]");
        
        if ($elements->length === 0) {
            Log::info('Nenhum elemento com classe tpl-weekday-img encontrado');
            return;
        }
        
        // Primeiro tenta obter a imagem como base64
        try {
            // Tenta usar o caminho completo do Storage
            $path = Storage::disk('public')->path($weekdayModel->image);
            if (!file_exists($path)) {
                // Tenta usar o caminho relativo ao storage/public
                $path = public_path('storage/' . $weekdayModel->image);
                if (!file_exists($path)) {
                    Log::warning('Imagem do dia da semana não encontrada:', [
                        'caminho_storage' => Storage::disk('public')->path($weekdayModel->image),
                        'caminho_public' => public_path('storage/' . $weekdayModel->image),
                        'imagem_atributo' => $weekdayModel->image
                    ]);
                    return;
                }
            }
            
            // Lê o conteúdo da imagem e converte para base64
            $mime = mime_content_type($path);
            $imageData = file_get_contents($path);
            $base64 = 'data:' . $mime . ';base64,' . base64_encode($imageData);
            
            Log::info('Imagem do dia da semana convertida para base64:', [
                'mime' => $mime,
                'tamanho' => strlen($imageData),
                'base64_tamanho' => strlen($base64)
            ]);
            
            // Processa cada elemento encontrado
            foreach ($elements as $element) {
                if (!$element instanceof \DOMElement) continue;
                
                // Se for elemento <img>, adiciona o src
                if ($element->nodeName === 'img') {
                    $element->setAttribute('src', $base64);
                }
                // Se for <div> ou outro elemento, adiciona background-image via style
                else {
                    $style = $element->getAttribute('style') ?: '';
                    
                    // Remove background-image existente, se houver
                    $style = preg_replace('/background-image\s*:\s*[^;]+;?/', '', $style);
                    
                    // Adiciona o novo background-image
                    $style .= (strlen($style) > 0 && substr($style, -1) !== ';') ? '; ' : '';
                    $style .= "background-image: url('{$base64}'); background-size: contain; background-repeat: no-repeat; background-position: center;";
                    
                    $element->setAttribute('style', $style);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Erro ao processar imagem do dia da semana:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
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

    /**
     * Substitui marcadores no template
     *
     * @param string $html Conteúdo do template
     * @param array $data Dados para substituição
     * @return string Template com marcadores substituídos
     */
    private function replaceMarkers(string $html, array $data): string
    {
        // Substitui marcadores básicos
        foreach ($data as $key => $value) {
            $html = str_replace("{{$key}}", $value, $html);
        }
        
        return $html;
    }
} 