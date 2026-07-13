<?php

namespace App\Services\Auth;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Str;
use stdClass;
use UnexpectedValueException;

// Encodage/decodage des JWT (§7.1, §13.1). Secret dedie (config('siarn.jwt.secret')),
// distinct de APP_KEY. Chaque token porte un jti (identifiant unique) permettant
// la revocation via la table sessions_jwt (App\Models\SessionJwt).
class JwtService
{
    private const ALGORITHME = 'HS256';

    public function emettre(string $utilisateurId, string $type, int $ttlMinutes, array $claimsSupplementaires = []): array
    {
        $maintenant = time();
        $jti = (string) Str::uuid();

        $claims = array_merge([
            'sub' => $utilisateurId,
            'typ' => $type, // 'acces' | 'mfa'
            'jti' => $jti,
            'iat' => $maintenant,
            'exp' => $maintenant + ($ttlMinutes * 60),
        ], $claimsSupplementaires);

        $token = JWT::encode($claims, config('siarn.jwt.secret'), self::ALGORITHME);

        return ['token' => $token, 'jti' => $jti, 'expire_a' => $maintenant + ($ttlMinutes * 60)];
    }

    /**
     * Decode et verifie la signature/expiration. Retourne null si le token
     * est invalide, expire, ou mal signe (jamais d'exception qui fuite vers
     * l'appelant : un JWT invalide est un cas attendu, pas une erreur systeme).
     */
    public function decoder(string $token): ?stdClass
    {
        try {
            return JWT::decode($token, new Key(config('siarn.jwt.secret'), self::ALGORITHME));
        } catch (ExpiredException|SignatureInvalidException|UnexpectedValueException) {
            return null;
        }
    }
}
