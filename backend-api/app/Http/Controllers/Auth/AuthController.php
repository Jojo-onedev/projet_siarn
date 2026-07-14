<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\AuthentificationException;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthentificationService;
use App\Services\Auth\JwtService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthentificationService $authentificationService,
        private readonly JwtService $jwtService,
    ) {}

    public function connexion(Request $request)
    {
        $donnees = $request->validate([
            'email' => ['required', 'email'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        try {
            $resultat = $this->authentificationService->connecter(
                $donnees['email'],
                $donnees['mot_de_passe'],
                $request->ip(),
                $request->userAgent(),
            );
        } catch (AuthentificationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statutHttp);
        }

        if ($resultat['statut'] === 'mfa_requis') {
            return response()->json(['statut' => 'mfa_requis', 'mfa_token' => $resultat['mfa_token']]);
        }

        return response()->json([
            'statut' => 'connecte',
            'token' => $resultat['token'],
            'utilisateur' => $this->presenterUtilisateur($resultat['utilisateur']),
        ]);
    }

    public function verifierMfa(Request $request)
    {
        $donnees = $request->validate([
            'mfa_token' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        try {
            $resultat = $this->authentificationService->verifierMfa(
                $donnees['mfa_token'],
                $donnees['code'],
                $request->ip(),
                $request->userAgent(),
            );
        } catch (AuthentificationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statutHttp);
        }

        return response()->json([
            'statut' => 'connecte',
            'token' => $resultat['token'],
            'utilisateur' => $this->presenterUtilisateur($resultat['utilisateur']),
        ]);
    }

    public function deconnexion(Request $request)
    {
        $claims = $this->jwtService->decoder($request->bearerToken() ?? '');
        if ($claims) {
            $this->authentificationService->deconnecter($claims->jti, $request->user());
        }

        return response()->json(['message' => 'Deconnecte.']);
    }

    public function moi(Request $request)
    {
        return response()->json($this->presenterUtilisateur($request->user()));
    }

    public function changerMotDePasse(Request $request)
    {
        $donnees = $request->validate([
            'mot_de_passe_actuel' => ['required', 'string'],
            'nouveau_mot_de_passe' => ['required', 'string', 'min:12'],
        ]);

        try {
            $this->authentificationService->changerMotDePasse(
                $request->user(),
                $donnees['mot_de_passe_actuel'],
                $donnees['nouveau_mot_de_passe'],
            );
        } catch (AuthentificationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statutHttp);
        }

        return response()->json(['message' => 'Mot de passe modifie avec succes.']);
    }

    private function presenterUtilisateur($utilisateur): array
    {
        return [
            'id' => $utilisateur->id,
            'nom' => $utilisateur->nom,
            'prenom' => $utilisateur->prenom,
            'email' => $utilisateur->email,
            'role' => $utilisateur->role->value,
            'statut_mfa' => $utilisateur->statut_mfa,
        ];
    }
}
