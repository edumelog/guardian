<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Symfony\Component\CssSelector\CssSelectorConverter;

class PrintTemplateController extends Controller
{
    public function index()
    {
        Log::info('Iniciando listagem de templates');
        
        // Lista todos os diretórios no storage/templates
        $allDirs = Storage::disk('public')->directories('templates');
        Log::info('Diretórios encontrados em templates:', ['directories' => $allDirs]);
        
        // Lista todos os diretórios de templates disponíveis
        $templates = collect(Storage::disk('public')->directories('templates'))
            ->filter(function ($dir) {
                Log::info('Verificando diretório:', ['dir' => $dir]);
                
                // Verifica se existe um arquivo index.html no diretório ou em qualquer subdiretório
                if (Storage::disk('public')->exists("{$dir}/index.html")) {
                    Log::info('Arquivo index.html encontrado no diretório raiz', ['dir' => $dir]);
                    return true;
                }
                
                // Verifica em subdiretórios
                $subdirs = Storage::disk('public')->directories($dir);
                Log::info('Subdiretórios encontrados:', ['dir' => $dir, 'subdirs' => $subdirs]);
                
                foreach ($subdirs as $subdir) {
                    if (Storage::disk('public')->exists("{$subdir}/index.html")) {
                        Log::info('Arquivo index.html encontrado no subdiretório', ['subdir' => $subdir]);
                        return true;
                    }
                    
                    // Verifica em subdiretórios de segundo nível
                    $subsubdirs = Storage::disk('public')->directories($subdir);
                    Log::info('Subdiretórios de segundo nível encontrados:', ['subdir' => $subdir, 'subsubdirs' => $subsubdirs]);
                    
                    foreach ($subsubdirs as $subsubdir) {
                        if (Storage::disk('public')->exists("{$subsubdir}/index.html")) {
                            Log::info('Arquivo index.html encontrado no subdiretório de segundo nível', ['subsubdir' => $subsubdir]);
                            return true;
                        }
                    }
                }
                
                Log::info('Nenhum arquivo index.html encontrado no diretório ou subdiretórios', ['dir' => $dir]);
                return false;
            })
            ->map(function ($dir) {
                $name = basename($dir);
                $zipName = $name . '.zip'; // Adiciona a extensão .zip ao nome do diretório
                
                Log::info('Processando template:', ['dir' => $dir, 'name' => $name, 'zipName' => $zipName]);
                
                // Encontra o caminho para o arquivo index.html
                $indexPath = null;
                
                // Verifica no diretório raiz
                if (Storage::disk('public')->exists("{$dir}/index.html")) {
                    $indexPath = "/storage/{$dir}/index.html";
                    Log::info('Arquivo index.html encontrado no diretório raiz', ['dir' => $dir, 'indexPath' => $indexPath]);
                } else {
                    // Verifica em subdiretórios
                    $subdirs = Storage::disk('public')->directories($dir);
                    foreach ($subdirs as $subdir) {
                        if (Storage::disk('public')->exists("{$subdir}/index.html")) {
                            $indexPath = "/storage/{$subdir}/index.html";
                            Log::info('Arquivo index.html encontrado no subdiretório', ['subdir' => $subdir, 'indexPath' => $indexPath]);
                            break;
                        }
                        
                        // Verifica em subdiretórios de segundo nível
                        $subsubdirs = Storage::disk('public')->directories($subdir);
                        foreach ($subsubdirs as $subsubdir) {
                            if (Storage::disk('public')->exists("{$subsubdir}/index.html")) {
                                $indexPath = "/storage/{$subsubdir}/index.html";
                                Log::info('Arquivo index.html encontrado no subdiretório de segundo nível', ['subsubdir' => $subsubdir, 'indexPath' => $indexPath]);
                                break 2;
                            }
                        }
                    }
                }
                
                $template = [
                    'name' => $zipName, // Nome com extensão .zip para compatibilidade
                    'path' => $indexPath,
                    'slug' => $name,
                    'zipExists' => true // Sempre retorna true para compatibilidade
                ];
                
                Log::info('Template processado:', $template);
                
                return $template;
            })
            ->values();

        // Log para debug
        Log::info('Templates encontrados:', ['templates' => $templates]);

        return response()->json($templates);
    }

    public function upload(Request $request)
    {
        Log::info('Iniciando upload de template');
        try {
            Log::info('Validando arquivo...', [
                'has_file' => $request->hasFile('template'),
                'content_type' => $request->file('template')?->getMimeType(),
                'original_name' => $request->file('template')?->getClientOriginalName()
            ]);

            $request->validate([
                'template' => 'required|file|mimes:zip'
            ], [
                'template.required' => 'Nenhum arquivo foi enviado.',
                'template.file' => 'O arquivo enviado é inválido.',
                'template.mimes' => 'O arquivo deve ser um arquivo ZIP válido (extensão .zip).'
            ]);

            $file = $request->file('template');
            $originalFilename = $file->getClientOriginalName();
            $filenameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);
            
            // Verifica se o arquivo é realmente um ZIP
            if (!$file->getClientMimeType() || strpos($file->getClientMimeType(), 'zip') === false) {
                Log::warning('Arquivo não é um ZIP válido', [
                    'mime_type' => $file->getClientMimeType(),
                    'original_name' => $originalFilename
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'O arquivo enviado não é um arquivo ZIP válido.',
                    'type' => 'error'
                ], 422);
            }

            // Criar slug do nome do arquivo (remover acentos, espaços, etc.)
            $slug = Str::slug($filenameWithoutExt);
            $filename = $slug . '.zip';
            
            Log::info('Arquivo validado com sucesso', [
                'originalFilename' => $originalFilename,
                'slug' => $slug,
                'filename' => $filename
            ]);

            // Verifica se o arquivo já existe
            $isUpdate = Storage::disk('public')->exists("templates/{$filename}");
            Log::info('Verificação de arquivo existente', ['isUpdate' => $isUpdate]);
            
            // Se for uma atualização, remover pasta antiga
            if ($isUpdate) {
                // Exclui o arquivo ZIP
                Storage::disk('public')->delete("templates/{$filename}");
                Log::info('Template ZIP existente excluído');
                
                // Exclui a pasta descompactada se existir
                if (Storage::disk('public')->exists("templates/{$slug}")) {
                    Storage::disk('public')->deleteDirectory("templates/{$slug}");
                    Log::info('Diretório do template existente excluído');
                }
            }

            // Salva o arquivo ZIP
            $zipPath = $file->storeAs('templates', $filename, 'public');
            Log::info('Template ZIP salvo com sucesso', ['path' => $zipPath]);
            
            // Caminho completo para o arquivo ZIP
            $zipFullPath = Storage::disk('public')->path($zipPath);
            
            // Caminho para a pasta de destino
            $extractPath = Storage::disk('public')->path("templates/{$slug}");
            Log::info('Caminho de extração:', ['extractPath' => $extractPath, 'slug' => $slug]);

            // Verifica se o diretório existe
            if (File::exists($extractPath)) {
                Log::info('Diretório já existe, removendo:', ['path' => $extractPath]);
                File::deleteDirectory($extractPath);
            }

            // Cria a pasta de destino
            $result = File::makeDirectory($extractPath, 0755, true);
            Log::info('Criação do diretório:', ['resultado' => $result ? 'sucesso' : 'falha', 'path' => $extractPath]);

            // Verifica se o diretório foi criado
            if (!File::exists($extractPath)) {
                Log::error('Falha ao criar diretório:', ['path' => $extractPath]);
                throw new \Exception('Não foi possível criar o diretório para descompactar o arquivo.');
            }
            
            // Descompacta o arquivo
            $zip = new ZipArchive;
            $openResult = $zip->open($zipFullPath);
            
            if ($openResult === true) {
                Log::info('Descompactando arquivo ZIP', [
                    'source' => $zipFullPath,
                    'destination' => $extractPath
                ]);
                
                $extractResult = $zip->extractTo($extractPath);
                $zip->close();
                
                if ($extractResult) {
                    Log::info('Arquivo ZIP descompactado com sucesso');
                    
                    // Debug antes de processar os arquivos HTML
                    Log::info('Iniciando processamento dos arquivos HTML');
                    try {
                        $this->processHtmlFiles($extractPath, $slug);
                        Log::info('Processamento dos arquivos HTML concluído com sucesso');
                    } catch (\Exception $e) {
                        Log::error('Erro ao processar arquivos HTML', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                    
                    // Exclui o arquivo ZIP após descompactação bem-sucedida
                    Storage::disk('public')->delete($zipPath);
                    Log::info('Arquivo ZIP excluído após descompactação bem-sucedida', ['zipPath' => $zipPath]);
                    
                    $response = [
                        'success' => true,
                        'message' => $isUpdate ? 'Template atualizado com sucesso!' : 'Template enviado com sucesso!',
                        'type' => 'success',
                        'path' => Storage::url("templates/{$slug}"),
                        'name' => $filename,
                        'slug' => $slug
                    ];
                    Log::info('Enviando resposta de sucesso', $response);
                    
                    return response()->json($response);
                } else {
                    // Falha na extração - limpar arquivos
                    Storage::disk('public')->delete($zipPath);
                    if (File::exists($extractPath)) {
                        File::deleteDirectory($extractPath);
                    }
                    
                    Log::error('Falha ao extrair o arquivo ZIP');
                    return response()->json([
                        'success' => false,
                        'message' => 'Não foi possível extrair o arquivo ZIP. O arquivo pode estar corrompido.',
                        'type' => 'error'
                    ], 500);
                }
            } else {
                // Falha ao abrir o ZIP - limpar arquivos
                Storage::disk('public')->delete($zipPath);
                
                Log::error('Falha ao abrir o arquivo ZIP', ['error_code' => $openResult]);
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível abrir o arquivo ZIP. O arquivo pode estar corrompido.',
                    'type' => 'error'
                ], 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Erro de validação', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => $e->errors()['template'][0] ?? 'Erro na validação do arquivo.',
                'type' => 'error'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao processar template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ocorreu um erro ao processar o template: ' . $e->getMessage(),
                'type' => 'error'
            ], 500);
        }
    }

    /**
     * Processa as imagens no HTML, convertendo para base64
     * 
     * @param string $html Conteúdo HTML
     * @param string $extractPath Caminho do diretório do template
     * @param string $htmlDir Diretório do arquivo HTML atual
     * @return string HTML processado
     */
    private function processImages($html, $extractPath, $htmlDir)
    {
        Log::info('Iniciando processamento de imagens', [
            'extractPath' => $extractPath,
            'htmlDir' => $htmlDir
        ]);

        // Carrega o HTML no DOMDocument
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Processa todas as imagens
        $images = $doc->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            
            // Pula se já for base64 ou URL externa
            if (strpos($src, 'data:image/') === 0 || strpos($src, 'http://') === 0 || strpos($src, 'https://') === 0) {
                continue;
            }

            try {
                // Remove parâmetros de URL se existirem
                $src = preg_replace('/\?.*$/', '', $src);
                
                // Log do caminho original da imagem
                Log::info('Mantendo caminho original da imagem:', [
                    'src' => $src
                ]);
            } catch (\Exception $e) {
                Log::error('Erro ao processar imagem:', [
                    'src' => $src,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Converte de volta para string HTML
        $html = $doc->saveHTML();
        
        Log::info('Processamento de imagens concluído');
        
        return $html;
    }

    /**
     * Processa os arquivos HTML para corrigir caminhos relativos
     * 
     * @param string $extractPath Caminho completo para o diretório do template
     * @param string $slug Nome do template (slug)
     * @return void
     */
    private function processHtmlFiles($extractPath, $slug)
    {
        Log::info('Processando arquivos HTML do template', ['path' => $extractPath, 'slug' => $slug]);
        
        // Verifica se o diretório existe
        if (!File::exists($extractPath)) {
            Log::error('Diretório de extração não existe', ['path' => $extractPath]);
            return;
        }
        
        // Obtém a URL base da aplicação
        $baseUrl = rtrim(config('app.url'), '/');
        Log::info('URL base da aplicação:', ['baseUrl' => $baseUrl]);
        
        // Lista todos os arquivos no diretório para debug
        $allFiles = File::allFiles($extractPath);
        $fileList = [];
        foreach ($allFiles as $file) {
            $fileList[] = $file->getPathname();
        }
        Log::info('Todos os arquivos encontrados no diretório:', ['files' => $fileList]);
        
        // Procura todos os arquivos HTML no diretório extraído
        $htmlFiles = [];
        foreach ($allFiles as $file) {
            if (strtolower($file->getExtension()) === 'html' || strtolower($file->getExtension()) === 'htm') {
                $htmlFiles[] = $file->getPathname();
            }
        }
        
        Log::info('Arquivos HTML encontrados:', ['files' => $htmlFiles]);
        
        if (empty($htmlFiles)) {
            Log::warning('Nenhum arquivo HTML encontrado no template');
            return;
        }
        
        foreach ($htmlFiles as $htmlFile) {
            Log::info('Processando arquivo HTML:', ['file' => $htmlFile]);
            
            // Lê o conteúdo do arquivo
            $html = File::get($htmlFile);
            
            // Define o caminho base absoluto para o template com a URL base da aplicação
            $storagePath = "/storage/templates/{$slug}";
            $absolutePath = "{$baseUrl}{$storagePath}";
            
            Log::info('Caminhos para substituição:', [
                'storagePath' => $storagePath,
                'absolutePath' => $absolutePath
            ]);
            
            // Remove qualquer tag base existente
            $html = preg_replace('/<base[^>]*>/', '', $html);
            
            // Log do HTML antes do processamento
            Log::info('HTML antes do processamento:', ['html' => $html]);
            
            // Primeiro processa as imagens para converter para base64
            $html = $this->processImages($html, $extractPath, dirname($htmlFile));
            
            // Log do HTML após processamento de imagens
            Log::info('HTML após processamento de imagens:', ['html' => $html]);
            
            // Extrai links para arquivos CSS
            $cssFiles = [];
            preg_match_all('/<link[^>]*rel=["\']stylesheet["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $cssLink) {
                    Log::info('Link CSS encontrado:', ['link' => $cssLink]);
                    
                    // Determina o caminho completo do arquivo CSS
                    $cssPath = $this->resolveCssPath($cssLink, $extractPath, dirname($htmlFile));
                    
                    if ($cssPath && File::exists($cssPath)) {
                        $cssFiles[] = [
                            'link' => $cssLink,
                            'path' => $cssPath
                        ];
                        Log::info('Arquivo CSS encontrado:', ['path' => $cssPath]);
                    } else {
                        Log::warning('Arquivo CSS não encontrado:', ['link' => $cssLink, 'resolved_path' => $cssPath]);
                    }
                }
            }
            
            // Carrega e processa o conteúdo CSS
            $cssContent = '';
            foreach ($cssFiles as $cssFile) {
                $css = File::get($cssFile['path']);
                $cssContent .= $css . "\n";
                
                // Remove o link para o CSS do HTML
                $html = str_replace('<link rel="stylesheet" href="' . $cssFile['link'] . '">', '', $html);
                $html = str_replace("<link rel=\"stylesheet\" href='" . $cssFile['link'] . "'>", '', $html);
                $html = preg_replace('/<link[^>]*href=["\']' . preg_quote($cssFile['link'], '/') . '["\'][^>]*>/i', '', $html);
            }
            
            // Se encontrou CSS, converte para inline
            if (!empty($cssContent)) {
                Log::info('Conteúdo CSS encontrado, convertendo para inline');
                $html = $this->convertCssToInline($html, $cssContent);
            }
            
            /* Comentando as substituições de caminhos para manter os caminhos relativos originais
            // Agora substitui os caminhos que não foram convertidos para base64
            
            // Substitui caminhos que começam com ./
            $html = preg_replace('/href=["\']\.\/([^"\']*)["\']/', "href=\"{$absolutePath}/$1\"", $html);
            $html = preg_replace('/src=["\']\.\/([^"\']*)["\'](?![^<>]*base64)/', "src=\"{$absolutePath}/$1\"", $html);
            
            // Substitui caminhos relativos sem ./
            $html = preg_replace('/href=["\'](?!http|\/\/|\/storage|data:)([^"\'\/][^"\']*)["\']/', "href=\"{$absolutePath}/$1\"", $html);
            $html = preg_replace('/src=["\'](?!http|\/\/|\/storage|data:)([^"\'\/][^"\']*)["\'](?![^<>]*base64)/', "src=\"{$absolutePath}/$1\"", $html);
            
            // Substitui caminhos que começam com /
            $html = preg_replace('/href=["\']\/(?!storage|http)([^"\']*)["\']/', "href=\"{$absolutePath}/$1\"", $html);
            $html = preg_replace('/src=["\']\/(?!storage|http)([^"\']*)["\'](?![^<>]*base64)/', "src=\"{$absolutePath}/$1\"", $html);
            */
            
            // Log do HTML após todas as substituições
            Log::info('HTML após todas as substituições:', ['html' => $html]);
            
            // Salva o arquivo modificado
            File::put($htmlFile, $html);
            
            Log::info('Arquivo HTML processado com sucesso', [
                'file' => $htmlFile,
                'absolutePath' => $absolutePath
            ]);
        }
    }
    
    /**
     * Resolve o caminho completo para um arquivo CSS
     * 
     * @param string $cssLink Link para o arquivo CSS
     * @param string $extractPath Caminho de extração do template
     * @param string $htmlDir Diretório do arquivo HTML
     * @return string|null Caminho completo do arquivo CSS ou null se não encontrado
     */
    private function resolveCssPath($cssLink, $extractPath, $htmlDir)
    {
        Log::info('Resolvendo caminho CSS:', ['link' => $cssLink, 'extractPath' => $extractPath, 'htmlDir' => $htmlDir]);
        
        // Remove parâmetros de URL se existirem
        $cssLink = preg_replace('/\?.*$/', '', $cssLink);
        
        // Caminho absoluto no sistema de arquivos
        if (strpos($cssLink, '/') === 0) {
            $path = $extractPath . $cssLink;
            Log::info('Tentando caminho absoluto:', ['path' => $path]);
            return File::exists($path) ? $path : null;
        }
        
        // Caminho relativo começando com ./
        if (strpos($cssLink, './') === 0) {
            $path = $htmlDir . '/' . substr($cssLink, 2);
            Log::info('Tentando caminho relativo com ./:', ['path' => $path]);
            return File::exists($path) ? $path : null;
        }
        
        // Caminho relativo sem ./
        $path = $htmlDir . '/' . $cssLink;
        Log::info('Tentando caminho relativo simples:', ['path' => $path]);
        if (File::exists($path)) {
            return $path;
        }
        
        // Tenta encontrar no diretório raiz do template
        $path = $extractPath . '/' . $cssLink;
        Log::info('Tentando caminho na raiz do template:', ['path' => $path]);
        return File::exists($path) ? $path : null;
    }
    
    /**
     * Converte CSS para estilos inline nos elementos HTML
     * 
     * @param string $html Conteúdo HTML
     * @param string $css Conteúdo CSS
     * @return string HTML com estilos inline
     */
    private function convertCssToInline($html, $css)
    {
        Log::info('Iniciando conversão de CSS para inline');
        
        // Carrega o HTML em um DOMDocument
        $doc = new \DOMDocument();
        
        // Preserva espaços em branco e formatação
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = true;
        
        // Suprime erros de parsing HTML e mantém a codificação
        libxml_use_internal_errors(true);
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $errors = libxml_get_errors();
        if (!empty($errors)) {
            Log::warning('Erros ao carregar HTML:', ['errors' => $errors]);
        }
        libxml_clear_errors();
        
        // Parse o CSS
        $cssRules = $this->parseCssRules($css);
        Log::info('Regras CSS parseadas:', ['count' => count($cssRules)]);
        
        // Log das regras CSS para debug
        Log::debug('Regras CSS detalhadas:', ['rules' => $cssRules]);
        
        // Aplica as regras CSS aos elementos
        foreach ($cssRules as $selector => $styles) {
            try {
                // Converte o seletor CSS para XPath
                $xpath = new \DOMXPath($doc);
                $query = $this->cssToXPath($selector);
                
                Log::info('Processando seletor:', [
                    'seletor_css' => $selector,
                    'xpath_query' => $query
                ]);
                
                // Encontra os elementos que correspondem ao seletor
                $elements = $xpath->query($query);
                
                if ($elements && $elements->length > 0) {
                    Log::info('Elementos encontrados para o seletor:', [
                        'seletor' => $selector,
                        'quantidade' => $elements->length
                    ]);
                    
                    foreach ($elements as $element) {
                        // Certifica-se de que estamos trabalhando com um DOMElement
                        if ($element instanceof \DOMElement) {
                            // Obtém o estilo atual do elemento
                            $currentStyle = $element->getAttribute('style');
                            
                            // Combina com os novos estilos
                            $newStyle = $currentStyle ? $currentStyle . '; ' . $styles : $styles;
                            
                            // Remove espaços extras e ponto e vírgula duplicados
                            $newStyle = preg_replace('/;\s*;/', ';', trim($newStyle));
                            $newStyle = rtrim($newStyle, ';') . ';';
                            
                            // Define o atributo style
                            $element->setAttribute('style', $newStyle);
                            
                            Log::debug('Estilo aplicado ao elemento:', [
                                'elemento' => $element->nodeName,
                                'classe' => $element->getAttribute('class'),
                                'id' => $element->getAttribute('id'),
                                'estilo_anterior' => $currentStyle,
                                'estilo_novo' => $newStyle
                            ]);
                        }
                    }
                } else {
                    Log::warning('Nenhum elemento encontrado para o seletor:', [
                        'seletor' => $selector,
                        'xpath_query' => $query
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Erro ao aplicar estilo:', [
                    'selector' => $selector,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Converte de volta para string HTML preservando a formatação
        $inlineHtml = $doc->saveHTML();
        
        Log::info('Conversão de CSS para inline concluída');
        
        return $inlineHtml;
    }
    
    /**
     * Parseia regras CSS em um array de seletores e estilos
     * 
     * @param string $css Conteúdo CSS
     * @return array Array associativo de seletores e estilos
     */
    private function parseCssRules($css)
    {
        $rules = [];
        
        // Remove comentários
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove quebras de linha e espaços extras
        $css = preg_replace('/[\r\n\t]+/', ' ', $css);
        
        // Encontra todas as regras CSS
        preg_match_all('/([^{]+){([^}]+)}/s', $css, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            // Seletores (podem ser múltiplos, separados por vírgula)
            $selectors = array_map('trim', explode(',', trim($match[1])));
            
            // Estilos
            $styles = trim($match[2]);
            
            // Remove quebras de linha e espaços extras dos estilos
            $styles = preg_replace('/\s+/', ' ', $styles);
            
            // Remove ponto e vírgula extra no final se existir
            $styles = rtrim($styles, ';');
            
            foreach ($selectors as $selector) {
                // Ignora seletores vazios
                if (empty($selector)) {
                    continue;
                }
                
                // Adiciona ou combina estilos para o seletor
                if (isset($rules[$selector])) {
                    $rules[$selector] .= '; ' . $styles;
                } else {
                    $rules[$selector] = $styles;
                }
                
                Log::debug('Regra CSS processada:', [
                    'selector' => $selector,
                    'styles' => $styles
                ]);
            }
        }
        
        return $rules;
    }
    
    /**
     * Converte um seletor CSS para XPath
     * Usando a biblioteca Symfony CSS Selector para melhor compatibilidade
     * 
     * @param string $selector Seletor CSS
     * @return string Expressão XPath
     */
    private function cssToXPath($selector)
    {
        // Instancia o conversor
        $converter = new CssSelectorConverter();
        
        try {
            // Converter o seletor CSS para XPath
            $xpath = $converter->toXPath($selector);
            
            Log::info('Seletor CSS convertido para XPath:', [
                'seletor_css' => $selector,
                'xpath' => $xpath
            ]);
            
            return $xpath;
        } catch (\Exception $e) {
            Log::error('Erro ao converter seletor CSS para XPath:', [
                'seletor' => $selector,
                'erro' => $e->getMessage()
            ]);
            
            // Retorna um XPath que não encontrará nenhum elemento
            return '//null';
        }
    }

    public function delete($name)
    {
        Log::info('Solicitação para excluir template:', ['name' => $name]);
        
        // Extrai o nome base (sem extensão)
        $slug = pathinfo($name, PATHINFO_FILENAME);
        Log::info('Slug extraído:', ['slug' => $slug]);
        
        // Verifica se o diretório do template existe
        if (!Storage::disk('public')->exists("templates/{$slug}")) {
            Log::warning('Diretório do template não encontrado:', ['slug' => $slug]);
            return response()->json([
                'success' => false,
                'message' => 'Template não encontrado',
                'type' => 'error'
            ], 404);
        }

        try {
            // Exclui o arquivo ZIP se existir
            if (Storage::disk('public')->exists("templates/{$name}")) {
                Storage::disk('public')->delete("templates/{$name}");
                Log::info("Arquivo ZIP {$name} excluído");
            } else {
                Log::info("Arquivo ZIP {$name} não encontrado");
                
                // Tenta encontrar o arquivo ZIP pelo slug
                $zipFiles = Storage::disk('public')->files('templates');
                $matchingZips = array_filter($zipFiles, function($file) use ($slug) {
                    return pathinfo($file, PATHINFO_FILENAME) === $slug;
                });
                
                if (!empty($matchingZips)) {
                    foreach ($matchingZips as $zipFile) {
                        Storage::disk('public')->delete($zipFile);
                        Log::info("Arquivo ZIP alternativo excluído", ['file' => $zipFile]);
                    }
                }
            }
            
            // Lista os arquivos no diretório antes de excluir
            $files = Storage::disk('public')->files("templates/{$slug}");
            Log::info("Arquivos no diretório antes da exclusão:", ['files' => $files]);
            
            // Exclui o diretório descompactado
            Storage::disk('public')->deleteDirectory("templates/{$slug}");
            Log::info("Diretório {$slug} excluído");

            return response()->json([
                'success' => true,
                'message' => 'Template excluído com sucesso!',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir o template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir o template: ' . $e->getMessage(),
                'type' => 'error'
            ], 500);
        }
    }

    public function getTemplate($name)
    {
        Log::info('Solicitando template:', ['name' => $name]);
        
        // Extrai o nome base (sem extensão)
        $slug = pathinfo($name, PATHINFO_FILENAME);
        Log::info('Slug extraído:', ['name' => $name, 'slug' => $slug]);
        
        // Procura o diretório descompactado no storage/app/public/templates
        if (!Storage::disk('public')->exists("templates/{$slug}")) {
            Log::warning('Diretório do template não encontrado:', ['slug' => $slug]);
            return response()->json([
                'success' => false,
                'message' => 'Template não encontrado',
                'type' => 'error'
            ], 404);
        }
        
        // Verifica se existe um arquivo index.html na pasta do template
        if (!Storage::disk('public')->exists("templates/{$slug}/index.html")) {
            Log::warning('Arquivo index.html não encontrado no template:', ['slug' => $slug]);
            return response()->json([
                'success' => false,
                'message' => 'Arquivo index.html não encontrado no template',
                'type' => 'error'
            ], 404);
        }

        $path = Storage::disk('public')->path("templates/{$slug}/index.html");
        Log::info('Template encontrado:', ['path' => $path]);
        return response()->file($path);
    }
} 