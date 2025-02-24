<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PrintTemplateController extends Controller
{
    public function index()
    {
        // Lista todos os templates disponíveis
        $templates = collect(Storage::disk('public')->files('templates'))
            ->filter(function ($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'html';
            })
            ->map(function ($file) {
                $name = basename($file);
                return [
                    'name' => $name,
                    'path' => '/storage/' . $file,
                    'isDefault' => $name === 'default.html'
                ];
            })
            ->values();

        // Sempre inclui o template padrão
        if (!$templates->contains('name', 'default.html')) {
            $templates->prepend([
                'name' => 'default.html',
                'path' => '/templates/default.html',
                'isDefault' => true
            ]);
        }

        return response()->json($templates);
    }

    public function upload(Request $request)
    {
        \Log::info('Iniciando upload de template');
        try {
            \Log::info('Validando arquivo...', [
                'has_file' => $request->hasFile('template'),
                'content_type' => $request->file('template')?->getMimeType(),
                'original_name' => $request->file('template')?->getClientOriginalName()
            ]);

            $request->validate([
                'template' => 'required|file|mimes:html,htm'
            ], [
                'template.required' => 'Nenhum arquivo foi enviado.',
                'template.file' => 'O arquivo enviado é inválido.',
                'template.mimes' => 'O arquivo deve ser um template HTML válido (extensão .html ou .htm).'
            ]);

            $file = $request->file('template');
            $filename = $file->getClientOriginalName();
            \Log::info('Arquivo validado com sucesso', ['filename' => $filename]);

            // Impede sobrescrita do template padrão
            if (strtolower($filename) === 'default.html') {
                \Log::warning('Tentativa de sobrescrever template padrão');
                return response()->json([
                    'success' => false,
                    'message' => 'Não é permitido sobrescrever o template padrão (default.html)',
                    'type' => 'error'
                ], 403);
            }

            // Verifica se o arquivo já existe
            $isUpdate = Storage::disk('public')->exists("templates/{$filename}");
            \Log::info('Verificação de arquivo existente', ['isUpdate' => $isUpdate]);
            
            if ($isUpdate) {
                // Se existir, deleta primeiro
                Storage::disk('public')->delete("templates/{$filename}");
                \Log::info('Template existente excluído');
            }

            $path = $file->storeAs('templates', $filename, 'public');
            \Log::info('Template salvo com sucesso', ['path' => $path]);

            $response = [
                'success' => true,
                'message' => $isUpdate ? 'Template atualizado com sucesso!' : 'Template enviado com sucesso!',
                'type' => 'success',
                'path' => Storage::url($path),
                'name' => $filename
            ];
            \Log::info('Enviando resposta de sucesso', $response);
            
            return response()->json($response);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Erro de validação', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => $e->errors()['template'][0] ?? 'Erro na validação do arquivo.',
                'type' => 'error'
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Erro ao processar template', [
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
        // Impede exclusão do template padrão
        if (strtolower($name) === 'default.html') {
            return response()->json([
                'success' => false,
                'message' => 'Não é permitido excluir o template padrão (default.html)',
                'type' => 'error'
            ], 403);
        }

        // Verifica se o template existe
        if (!Storage::disk('public')->exists("templates/{$name}")) {
            return response()->json([
                'success' => false,
                'message' => 'Template não encontrado',
                'type' => 'error'
            ], 404);
        }

        try {
            // Exclui o template
            Storage::disk('public')->delete("templates/{$name}");

            return response()->json([
                'success' => true,
                'message' => 'Template excluído com sucesso!',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir o template: ' . $e->getMessage(),
                'type' => 'error'
            ], 500);
        }
    }

    public function getTemplate($name)
    {
        // Se for o template padrão, retorna do diretório public
        if ($name === 'default.html') {
            $path = public_path("templates/{$name}");
            if (!file_exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template padrão não encontrado',
                    'type' => 'error'
                ], 404);
            }
            return response()->file($path);
        }

        // Procura o template no storage/app/public/templates
        if (!Storage::disk('public')->exists("templates/{$name}")) {
            return response()->json([
                'success' => false,
                'message' => 'Template não encontrado',
                'type' => 'error'
            ], 404);
        }

        $path = Storage::disk('public')->path("templates/{$name}");
        return response()->file($path);
    }
} 