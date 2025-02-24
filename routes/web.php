<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PrintTemplateController;
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

    // Rotas para templates de impressão
    Route::get('/print-templates', [PrintTemplateController::class, 'index']);
    Route::post('/print-templates/upload', [PrintTemplateController::class, 'upload']);
    Route::get('/print-templates/{name}', [PrintTemplateController::class, 'getTemplate']);
    Route::delete('/print-templates/{name}', [PrintTemplateController::class, 'delete']);
});

require __DIR__.'/auth.php';
URL::forceScheme('https');
