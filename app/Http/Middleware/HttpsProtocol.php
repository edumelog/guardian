<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class HttpsProtocol
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->secure() && App::environment() !== 'local') {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
} 