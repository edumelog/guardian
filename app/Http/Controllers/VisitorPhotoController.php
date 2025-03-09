<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitorPhotoController extends Controller
{
    /**
     * Serve a foto do visitante a partir do disco private
     * 
     * @param Request $request
     * @param string $filename
     * @return StreamedResponse
     */
    public function show(Request $request, string $filename)
    {
        // Verifica se o usuário está autenticado
        if (!Auth::check()) {
            abort(403, 'Acesso não autorizado.');
        }

        $path = 'visitors-photos/' . $filename;

        // Verifica se o arquivo existe
        if (!Storage::disk('private')->exists($path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        // Obtém o conteúdo do arquivo
        $file = Storage::disk('private')->get($path);
        
        // Determina o tipo MIME com base na extensão do arquivo
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ];
        $type = $mimeTypes[$extension] ?? 'application/octet-stream';

        // Retorna o arquivo como uma resposta
        return Response::make($file, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
} 