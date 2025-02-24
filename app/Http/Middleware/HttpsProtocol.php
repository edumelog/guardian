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

        $request->server->set('HTTPS', 'on');
        $request->server->set('SERVER_PORT', 443);

        if ($request->header('x-forwarded-proto') === 'http') {
            $request->server->set('HTTP_X_FORWARDED_PROTO', 'https');
            $request->server->set('HTTP_X_FORWARDED_PORT', 443);
        }

        return $next($request);
    }
} 