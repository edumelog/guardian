<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PrintTemplateController;
use App\Http\Controllers\VisitorPhotoController;
use App\Http\Controllers\CredentialPrintController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

Route::get('/', function () {
    // return view('welcome');
    // redirect para o path 'dashboard/login'
    return redirect('dashboard/login');
});

// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Rotas para templates de impressão que requerem autenticação
    Route::post('/print-templates/upload', [PrintTemplateController::class, 'upload']);
    Route::delete('/print-templates/{name}', [PrintTemplateController::class, 'delete']);
    
    // Rota para acessar fotos dos visitantes de forma segura
    Route::get('/visitor-photo/{filename}', [VisitorPhotoController::class, 'show'])->name('visitor.photo');

    // Rotas para preview de impressão
    Route::post('/credentials/{visitor}/preview', [CredentialPrintController::class, 'preview'])
        ->name('credential.preview');
        
    Route::get('/credentials/preview/{preview}', [CredentialPrintController::class, 'showPreview'])
        ->name('credential.preview.pdf')
        ->middleware('signed');
});

// Rotas para templates de impressão que não requerem autenticação
Route::get('/print-templates', [PrintTemplateController::class, 'index']);
Route::get('/print-templates/{name}', [PrintTemplateController::class, 'getTemplate']);

Route::prefix('qz')->group(function () {
    Route::post('sign', [App\Http\Controllers\QZSignController::class, 'sign']);
    Route::get('certificate', [App\Http\Controllers\QZPrintController::class, 'getCertificate']);
});

// Rota para ler o arquivo PDF temporário
Route::get('/credentials/pdf/{path}', function (string $path) {
    // Verifica se o caminho é válido e está dentro do diretório de PDFs temporários
    $fullPath = urldecode($path);
    if (!str_starts_with($fullPath, storage_path('app/private/temp/previews/'))) {
        abort(403, 'Acesso negado');
    }

    if (!File::exists($fullPath)) {
        abort(404, 'Arquivo não encontrado');
    }

    // Retorna o arquivo PDF
    return Response::file($fullPath, [
        'Content-Type' => 'application/pdf',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0'
    ]);
})->middleware(['auth', 'verified'])->name('credentials.pdf');

require __DIR__.'/auth.php';
URL::forceScheme('https');
