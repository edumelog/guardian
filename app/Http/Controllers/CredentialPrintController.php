<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Services\CredentialPrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CredentialPrintController extends Controller
{
    /**
     * Gera um preview do PDF da credencial
     */
    public function preview(
        Request $request,
        Visitor $visitor,
        CredentialPrintService $printService
    ) {
        Log::info('Recebida requisição de preview de credencial', [
            'visitor_id' => $visitor->id,
            'printer_config' => $request->printer_config
        ]);

        try {
            $validated = $request->validate([
                'printer_config' => ['required', 'array'],
                'printer_config.template' => ['required', 'string'],
                'printer_config.printer' => ['required', 'string'],
                'printer_config.printOptions' => ['required', 'array'],
                'printer_config.printOptions.pageWidth' => ['required', 'numeric'],
                'printer_config.printOptions.pageHeight' => ['required', 'numeric'],
            ]);

            Log::info('Validação dos dados de impressão OK');

            $preview = $printService->generatePreview($visitor, $validated['printer_config']);
            
            Log::info('Preview gerado com sucesso', [
                'preview_url' => $preview['preview_url']
            ]);

            return response()->json($preview);

        } catch (ValidationException $e) {
            Log::warning('Erro de validação', [
                'errors' => $e->errors(),
                'visitor_id' => $visitor->id
            ]);
            
            return response()->json([
                'message' => 'Dados de impressão inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar preview', [
                'error' => $e->getMessage(),
                'visitor_id' => $visitor->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Erro ao gerar preview da credencial',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe o PDF de preview gerado
     */
    public function showPreview(Request $request, string $preview)
    {
        Log::info('Recebida requisição para exibir preview', [
            'preview_id' => $preview
        ]);

        if (!$request->hasValidSignature()) {
            Log::warning('Tentativa de acesso com assinatura inválida', [
                'preview_id' => $preview,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'URL de preview inválida ou expirada'
            ], 401);
        }

        $path = storage_path("app/temp/previews/{$preview}.pdf");
        
        if (!File::exists($path)) {
            Log::warning('Arquivo de preview não encontrado', [
                'preview_id' => $preview,
                'path' => $path
            ]);
            
            return response()->json([
                'message' => 'Preview não encontrado'
            ], 404);
        }

        Log::info('Retornando arquivo de preview', [
            'preview_id' => $preview,
            'file_size' => File::size($path)
        ]);

        return response()->file($path);
    }
} 