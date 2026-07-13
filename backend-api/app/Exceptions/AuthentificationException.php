<?php

namespace App\Exceptions;

use RuntimeException;

// Erreur d'authentification "attendue" (identifiants invalides, compte
// verrouille, MFA invalide...) : distincte des erreurs systeme, toujours
// renvoyee au client avec un statut HTTP adapte (cf. AuthController).
class AuthentificationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statutHttp = 401)
    {
        parent::__construct($message);
    }
}
