<?php

namespace App\Services;

use App\Models\QZCertificate;
use Exception;

class QZSignatureService
{
    private $privateKey;

    public function __construct()
    {
        $this->loadPrivateKey();
    }

    private function loadPrivateKey()
    {
        $qzCertificate = QZCertificate::latest()->first();
        
        if (!$qzCertificate) {
            throw new Exception('Certificados QZ não encontrados. Configure-os primeiro.');
        }

        if (!file_exists($qzCertificate->private_key_path_full)) {
            throw new Exception('Arquivo de chave privada não encontrado.');
        }

        $this->privateKey = $qzCertificate->private_key_path_full;
    }

    public function sign(string $data): string
    {
        if (!$this->privateKey || !file_exists($this->privateKey)) {
            throw new Exception('Chave privada não encontrada');
        }

        $signature = null;
        $pkeyid = openssl_pkey_get_private("file://" . $this->privateKey);

        if (!$pkeyid) {
            throw new Exception('Não foi possível carregar a chave privada');
        }

        try {
            // Cria a assinatura
            openssl_sign($data, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
            
            // Libera a chave da memória
            openssl_free_key($pkeyid);
            
            if ($signature === null) {
                throw new Exception('Falha ao criar assinatura');
            }

            // Retorna a assinatura em base64
            return base64_encode($signature);
        } catch (Exception $e) {
            if ($pkeyid) {
                openssl_free_key($pkeyid);
            }
            throw $e;
        }
    }
} 