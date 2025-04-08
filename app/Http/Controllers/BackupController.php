<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador para manipulação de backups
 * 
 * Este controlador lida com operações relacionadas a backups, como download
 * de arquivos de backup.
 */
class BackupController extends Controller
{
    /**
     * Faz o download de um arquivo de backup
     *
     * @param Request $request
     * @param string $filename Nome do arquivo a ser baixado
     * @return StreamedResponse
     */
    public function download(Request $request, $filename)
    {
        // Verifica se o usuário está autenticado
        if (!Auth::check()) {
            abort(403, 'Acesso não autorizado.');
        }

        // Verificação de permissão seria feita aqui em um ambiente real
        // Na implementação final, integraria com o sistema de permissões do Filament Shield

        // O parâmetro filename pode incluir o diretório "Guardian/" no início
        // Verificamos se o arquivo existe
        if (!Storage::disk('backups')->exists($filename)) {
            abort(404, 'Arquivo não encontrado: ' . $filename);
        }

        // Retorna o arquivo para download
        return response()->download(Storage::disk('backups')->path($filename), basename($filename));
    }
} 