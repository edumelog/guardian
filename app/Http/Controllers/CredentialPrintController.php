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
     * Gera o PDF da credencial para impressão
     */
    public function generatePdf(Visitor $visitor, Request $request)
    {
        $validated = $request->validate([
            'printer_config' => ['required', 'array']
        ]);

        try {
            // Desativa temporariamente o output buffering
            while (ob_get_level()) ob_end_clean();

            // Gera o PDF
            $result = $this->printService->generatePdf($visitor, $validated['printer_config']);

            // Garante que não há nenhum output antes do JSON
            ob_start();
            $response = response()->json($result);
            $output = ob_get_clean();

            if (!empty($output)) {
                Log::warning('Output inesperado antes do JSON', ['output' => $output]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Erro ao gerar PDF', [
                'visitor_id' => $visitor->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 