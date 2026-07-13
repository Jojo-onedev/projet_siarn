<?php

use App\Models\Utilisateur;

return [

    /*
    |--------------------------------------------------------------------------
    | Configuration SIARN : authentification API par JWT (§7.1, §13.1 du PRD)
    |--------------------------------------------------------------------------
    |
    | Pas de guard "web" a base de session : le frontend React consomme l'API
    | de maniere stateless via un token porteur (Bearer). Le guard "jwt" est
    | enregistre par App\Providers\AppServiceProvider (Auth::extend).
    |
    */

    'defaults' => [
        'guard' => 'api',
        'passwords' => null,
    ],

    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => 'utilisateurs',
        ],
    ],

    'providers' => [
        'utilisateurs' => [
            'driver' => 'eloquent',
            'model' => Utilisateur::class,
        ],
    ],

    // Reinitialisation de mot de passe : hors perimetre de cette iteration
    // (comptes provisionnes par l'administrateur, cf. §5 RBAC "Gerer roles").
    'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
