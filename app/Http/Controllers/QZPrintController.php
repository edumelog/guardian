<?php

namespace App\Http\Controllers;

use App\Services\QZSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QZPrintController extends Controller
{
    private $signatureService;

    public function __construct(QZSignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
    }

    public function sign(Request $request): JsonResponse
    {
        try {
            $req = json_decode(file_get_contents('php://input'));
            if (!$req) {
                throw new \Exception('Request inválido');
            }

            $signature = $this->signatureService->sign($req);

            return response()->json([
                'result' => $signature
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCertificate(): JsonResponse
    {
        try {
            $qzCertificate = \App\Models\QZCertificate::latest()->first();
            
            if (!$qzCertificate) {
                throw new \Exception('Certificado não encontrado');
            }

            $certificate = file_get_contents($qzCertificate->digital_certificate_path_full);

            return response()->json([
                'result' => $certificate
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 