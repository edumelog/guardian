<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PrintTemplateController;
use App\Http\Controllers\VisitorPhotoController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

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
});

// Rotas para templates de impressão que não requerem autenticação
Route::get('/print-templates', [PrintTemplateController::class, 'index']);
Route::get('/print-templates/{name}', [PrintTemplateController::class, 'getTemplate']);

Route::prefix('qz')->group(function () {
    Route::post('sign', [App\Http\Controllers\QZSignController::class, 'sign']);
    Route::get('certificate', [App\Http\Controllers\QZPrintController::class, 'getCertificate']);
});

require __DIR__.'/auth.php';
URL::forceScheme('https');
