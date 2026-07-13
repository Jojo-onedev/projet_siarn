<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// §13.1 : MFA obligatoire pour les roles a privileges eleves. Bloque l'acces
// aux routes metier tant que le MFA n'est pas configure, meme si le token
// d'acces est valide (le login seul ne suffit pas pour ces roles tant que
// l'enrolement MFA n'est pas termine, cf. AuthentificationService::activerMfa).
class VerifieMfaConfiguree
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if ($utilisateur && $utilisateur->role->mfaObligatoire() && ! $utilisateur->statut_mfa) {
            return response()->json([
                'message' => 'Configuration du MFA requise avant de poursuivre.',
                'code' => 'MFA_REQUIS',
            ], 403);
        }

        return $next($request);
    }
}
