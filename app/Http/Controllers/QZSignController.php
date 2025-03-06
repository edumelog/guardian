<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QZSignController extends Controller
{
    public function sign(Request $request)
    {
        try {
            // Lê a chave privada do arquivo
            $privateKeyPath = Storage::disk('local')->path('private/private-key.pem');
            if (!file_exists($privateKeyPath)) {
                throw new \Exception('Chave privada não encontrada');
            }

            // Obtém a mensagem a ser assinada do corpo da requisição
            $toSign = file_get_contents('php://input');
            if (empty($toSign)) {
                throw new \Exception('Nenhuma mensagem para assinar');
            }

            // Carrega a chave privada
            $privateKey = openssl_get_privatekey(file_get_contents($privateKeyPath));
            if (!$privateKey) {
                throw new \Exception('Erro ao carregar chave privada');
            }

            // Assina a mensagem
            $signature = null;
            if (!openssl_sign($toSign, $signature, $privateKey, "sha512")) {
                throw new \Exception('Erro ao assinar mensagem');
            }

            // Retorna a assinatura em base64
            return response(base64_encode($signature))
                ->header('Content-Type', 'text/plain');

        } catch (\Exception $e) {
            return response($e->getMessage(), 500)
                ->header('Content-Type', 'text/plain');
        }
    }
} 