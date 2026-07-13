<?php

namespace App\Auth;

use App\Models\SessionJwt;
use App\Services\Auth\JwtService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

// Guard "jwt" (§7.1, §13.1) : verifie signature + expiration du token, PUIS
// verifie que sa session n'a pas ete revoquee (sessions_jwt) - un JWT seul
// n'est jamais suffisant pour une revocation immediate.
class JwtGuard implements Guard
{
    use GuardHelpers;

    public function __construct(
        UserProvider $provider,
        protected Request $request,
        protected JwtService $jwtService,
    ) {
        $this->provider = $provider;
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();
        if (! $token) {
            return null;
        }

        $claims = $this->jwtService->decoder($token);
        if (! $claims || ($claims->typ ?? null) !== 'acces') {
            return null;
        }

        $session = SessionJwt::where('jti', $claims->jti)->first();
        if (! $session || $session->revoque || $session->expire_a->isPast()) {
            return null;
        }

        $utilisateur = $this->provider->retrieveById($claims->sub);
        if (! $utilisateur || ! $utilisateur->actif) {
            return null;
        }

        return $this->user = $utilisateur;
    }

    public function validate(array $credentials = []): bool
    {
        // L'authentification par mot de passe/MFA est geree explicitement par
        // AuthentificationService, pas par la mecanique standard "Auth::attempt".
        return false;
    }
}
