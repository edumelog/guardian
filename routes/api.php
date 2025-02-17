<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Destination;

Route::get('/destinations/{destination}/parents', function (Destination $destination) {
    $parents = [];
    $current = $destination;
    
    while ($current->parent_id) {
        $parents[] = $current->parent_id;
        $current = $current->parent;
    }
    
    return response()->json($parents);
}); 