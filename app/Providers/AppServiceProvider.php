<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Contracts\View\View;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\TemplateParserService::class, function ($app) {
            return new \App\Services\TemplateParserService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS if in production or using Traefik with SSL
        // if($this->app->environment('production') || env('VITE_USE_TRAEFIK') === 'true') {
        //     URL::forceScheme('https');
        // }
        
        // if($this->app->environment('production') || env('VITE_USE_TRAEFIK') === 'true') {
        //     URL::forceRootUrl(config('app.url'));
        // }
        
        FilamentView::registerRenderHook(
            'panels::auth.login.form.before',
            fn (): View => view('filament.login_extra')
        );

        // Adiciona validação de usuário ativo no momento da autenticação
        Event::listen(Attempting::class, function (Attempting $event) {
            $credentials = $event->credentials;
            
            // Busca usuário pelo email
            $user = \App\Models\User::where('email', $credentials['email'])->first();
            
            // Verifica se o usuário existe e está inativo
            if ($user && !$user->is_active) {
                throw ValidationException::withMessages([
                    'email' => ['Sua conta está desativada. Entre em contato com o administrador.'],
                ]);
            }
        });
    }
}
