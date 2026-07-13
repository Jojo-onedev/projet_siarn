<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// §13.4 : en-tetes de securite renforces (OWASP). API JSON pure : pas de
// rendu HTML cote serveur, CSP la plus stricte possible.
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('X-Frame-Options', 'DENY');
        $reponse->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $reponse->headers->set('Content-Security-Policy', "default-src 'none'");
        $reponse->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $reponse;
    }
}
