<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Destination;
use App\Http\Controllers\PrintTemplateController;

Route::get('/destinations/{destination}/parents', function (Destination $destination) {
    $parents = [];
    $current = $destination;
    
    while ($current->parent_id) {
        $parents[] = $current->parent_id;
        $current = $current->parent;
    }
    
    return response()->json($parents);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Rotas para templates de impressão
Route::get('/print-templates', [PrintTemplateController::class, 'index']);
Route::post('/print-templates/upload', [PrintTemplateController::class, 'upload']);
Route::get('/print-templates/{name}', [PrintTemplateController::class, 'getTemplate']); 