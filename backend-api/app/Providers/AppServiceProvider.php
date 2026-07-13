<?php

namespace App\Providers;

use App\Auth\JwtGuard;
use App\Services\Auth\JwtService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::extend('jwt', function ($app, string $name, array $config) {
            return new JwtGuard(
                Auth::createUserProvider($config['provider']),
                $app['request'],
                $app->make(JwtService::class),
            );
        });

        // API stateless pure JSON : jamais de redirection vers une route
        // "login" (qui n'existe pas ici). Sans ceci, un token invalide/expire
        // provoque une RouteNotFoundException (500) au lieu d'un 401 JSON propre.
        Authenticate::redirectUsing(fn () => null);
    }
}
