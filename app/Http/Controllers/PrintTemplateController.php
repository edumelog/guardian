<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;
use Illuminate\Support\Facades\File;

class PrintTemplateController extends Controller
{
    public function index()
    {
        // Lista todos os diretórios de templates disponíveis
        $templates = collect(Storage::disk('public')->directories('templates'))
            ->filter(function ($dir) {
                // Filtra apenas diretórios que contêm um arquivo index.html
                return Storage::disk('public')->exists("{$dir}/index.html");
            })
            ->map(function ($dir) {
                $name = basename($dir);
                $zipName = $name . '.zip';
                
                // Verifica se o arquivo ZIP existe
                $zipExists = Storage::disk('public')->exists("templates/{$zipName}");
                
                return [
                    'name' => $zipName, // Mantém o nome com .zip para compatibilidade
                    'path' => '/storage/' . $dir . '/index.html',
                    'slug' => $name,
                    'isDefault' => $name === 'default',
                    'zipExists' => $zipExists
                ];
            })
            ->values();

        // Sempre inclui o template padrão
        if (!$templates->contains('slug', 'default')) {
            $templates->prepend([
                'name' => 'default.zip',
                'path' => '/templates/default/index.html',
                'slug' => 'default',
                'isDefault' => true,
                'zipExists' => false
            ]);
        }

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

            // Impede sobrescrita do template padrão
            if (strtolower($filename) === 'default.zip') {
                Log::warning('Tentativa de sobrescrever template padrão');
                return response()->json([
                    'success' => false,
                    'message' => 'Não é permitido sobrescrever o template padrão (default.zip)',
                    'type' => 'error'
                ], 403);
            }

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

    public function delete($name)
    {
        Log::info('Solicitação para excluir template:', ['name' => $name]);
        
        // Extrai o nome base (sem extensão)
        $slug = pathinfo($name, PATHINFO_FILENAME);
        Log::info('Slug extraído:', ['slug' => $slug]);
        
        // Impede exclusão do template padrão
        if (strtolower($slug) === 'default') {
            Log::warning('Tentativa de excluir template padrão');
            return response()->json([
                'success' => false,
                'message' => 'Não é permitido excluir o template padrão (default)',
                'type' => 'error'
            ], 403);
        }

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
        
        // Se for o template padrão, retorna do diretório public
        if ($name === 'default.zip') {
            $path = public_path("templates/default/index.html");
            Log::info('Buscando template padrão:', ['path' => $path]);
            
            if (!file_exists($path)) {
                Log::warning('Template padrão não encontrado:', ['path' => $path]);
                return response()->json([
                    'success' => false,
                    'message' => 'Template padrão não encontrado',
                    'type' => 'error'
                ], 404);
            }
            return response()->file($path);
        }
        
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