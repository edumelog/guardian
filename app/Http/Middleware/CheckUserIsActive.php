<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verifica se existe um usuário autenticado e se ele não está ativo
        if (Auth::check() && !Auth::user()->is_active) {
            // Faz logout do usuário
            Auth::logout();
            
            // Invalida a sessão e regenera o token CSRF
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            // Redireciona para a página de login com mensagem de erro
            return redirect()->route('login')->with('status', 'Sua conta está desativada. Entre em contato com o administrador.');
        }
        
        return $next($request);
    }
}
