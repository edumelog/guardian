<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->secure() && env('FORCE_HTTPS', true)) {
            URL::forceScheme('https');
            
            // Força o Livewire a usar HTTPS
            config(['livewire.asset_url' => str_replace('http://', 'https://', config('app.url'))]);
        }

        return $next($request);
    }
} 