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
    public function __construct(
        private CredentialPrintService $printService
    ) {}

    /**
     * Gera um preview do PDF da credencial
     */
    public function preview(Visitor $visitor, Request $request)
    {
        $validated = $request->validate([
            'printer_config' => ['required', 'array']
        ]);

        try {
            // Desativa temporariamente o output buffering
            while (ob_get_level()) ob_end_clean();

            // Gera o preview
            $preview = $this->printService->generatePreview($visitor, $validated['printer_config']);

            // Garante que não há nenhum output antes do JSON
            ob_start();
            $response = response()->json($preview);
            $output = ob_get_clean();

            if (!empty($output)) {
                Log::warning('Output inesperado antes do JSON', ['output' => $output]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Erro ao gerar preview', [
                'visitor_id' => $visitor->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
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