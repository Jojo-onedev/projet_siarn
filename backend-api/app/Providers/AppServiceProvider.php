<?php

namespace App\Providers;

use App\Auth\JwtGuard;
use App\Services\Auth\JwtService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

        // §13.4, E11 : limite anti brute-force par IP sur les endpoints
        // d'authentification. Seuil configurable (au lieu d'un throttle:N,1
        // fige dans les routes) : la suite de tests genere legitimement plus
        // de requetes de connexion par minute qu'un usage reel depuis une
        // seule IP (voir phpunit.xml : seuil releve en environnement de test).
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(config('siarn.securite.limite_connexion_par_minute'))->by($request->ip());
        });
    }
}
