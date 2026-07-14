<?php

namespace App\Services\Auth;

use App\Exceptions\AuthentificationException;
use App\Models\SessionJwt;
use App\Models\Utilisateur;
use App\Services\Audit\JournalAuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

// Orchestration complete de l'authentification (§7.1, §13.1) : verification
// mot de passe, verrouillage progressif anti brute-force, second facteur TOTP
// pour les roles a privileges eleves, emission/revocation de session JWT,
// et journalisation systematique (journal_connexions + journal_audit).
class AuthentificationService
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly Google2FA $google2fa,
        private readonly JournalAuditService $journalAudit,
    ) {}

    public function connecter(string $email, string $motDePasse, ?string $ip, ?string $userAgent): array
    {
        $utilisateur = Utilisateur::where('email', $email)->first();

        if (! $utilisateur || ! $utilisateur->actif) {
            $this->journaliserConnexion(null, $email, false, 'compte_inconnu_ou_inactif', $ip, $userAgent);
            throw new AuthentificationException('Identifiants invalides.');
        }

        if ($utilisateur->estVerrouille()) {
            $this->journaliserConnexion($utilisateur->id, $email, false, 'compte_verrouille', $ip, $userAgent);
            throw new AuthentificationException('Compte verrouille temporairement suite a plusieurs echecs. Reessayez plus tard.', 423);
        }

        if (! Hash::check($motDePasse, $utilisateur->getAuthPassword())) {
            $this->enregistrerEchec($utilisateur);
            $this->journaliserConnexion($utilisateur->id, $email, false, 'mdp_invalide', $ip, $userAgent);
            throw new AuthentificationException('Identifiants invalides.');
        }

        $utilisateur->tentatives_echec = 0;
        $utilisateur->save();

        if ($utilisateur->role->mfaObligatoire() && $utilisateur->statut_mfa) {
            $emission = $this->jwtService->emettre($utilisateur->id, 'mfa', config('siarn.jwt.ttl_mfa_minutes'));
            $this->journaliserConnexion($utilisateur->id, $email, true, null, $ip, $userAgent);

            return ['statut' => 'mfa_requis', 'mfa_token' => $emission['token']];
        }

        return $this->finaliserConnexion($utilisateur, $ip, $userAgent);
    }

    public function verifierMfa(string $mfaToken, string $code, ?string $ip, ?string $userAgent): array
    {
        $claims = $this->jwtService->decoder($mfaToken);
        if (! $claims || ($claims->typ ?? null) !== 'mfa') {
            throw new AuthentificationException('Session MFA invalide ou expiree, reconnectez-vous.');
        }

        $utilisateur = Utilisateur::find($claims->sub);
        if (! $utilisateur || ! $utilisateur->actif) {
            throw new AuthentificationException('Compte introuvable.');
        }

        if ($utilisateur->estVerrouille()) {
            throw new AuthentificationException('Compte verrouille temporairement.', 423);
        }

        $secretEnClair = Crypt::decryptString($utilisateur->secret_mfa);

        if (! $this->google2fa->verifyKey($secretEnClair, $code)) {
            $this->enregistrerEchec($utilisateur);
            $this->journaliserConnexion($utilisateur->id, $utilisateur->email, false, 'mfa_invalide', $ip, $userAgent);
            throw new AuthentificationException('Code de verification invalide.');
        }

        return $this->finaliserConnexion($utilisateur, $ip, $userAgent);
    }

    public function activerMfa(Utilisateur $utilisateur): array
    {
        $secretEnClair = $this->google2fa->generateSecretKey();
        $utilisateur->secret_mfa = Crypt::encryptString($secretEnClair);
        // statut_mfa reste false tant que confirmerMfa() n'a pas verifie un code valide.
        $utilisateur->save();

        return [
            'secret' => $secretEnClair,
            'uri_provisionnement' => $this->uriProvisionnement($utilisateur->email, $secretEnClair),
        ];
    }

    public function confirmerMfa(Utilisateur $utilisateur, string $code): bool
    {
        if (! $utilisateur->secret_mfa) {
            throw new AuthentificationException('Aucune procedure d\'activation MFA en cours : appelez /mfa/activer d\'abord.', 400);
        }

        $secretEnClair = Crypt::decryptString($utilisateur->secret_mfa);
        if (! $this->google2fa->verifyKey($secretEnClair, $code)) {
            throw new AuthentificationException('Code de verification invalide.');
        }

        $utilisateur->statut_mfa = true;
        $utilisateur->save();

        $this->journalAudit->enregistrer('utilisateur.mfa_active', $utilisateur->id, 'utilisateur', $utilisateur->id);

        return true;
    }

    public function deconnecter(string $jti, Utilisateur $utilisateur): void
    {
        SessionJwt::where('jti', $jti)->update(['revoque' => true]);
        $this->journalAudit->enregistrer('utilisateur.deconnexion', $utilisateur->id, 'utilisateur', $utilisateur->id);
    }

    /**
     * Changement de mot de passe self-service - jusqu'ici absent (trouve en
     * revue manuelle) : une fois le mot de passe defini a la creation du
     * compte, aucune route ne permettait a l'utilisateur de le changer
     * lui-meme. Exige le mot de passe actuel (§13.1, pas de changement sur
     * la seule base d'un jeton d'acces deja valide).
     */
    public function changerMotDePasse(Utilisateur $utilisateur, string $motDePasseActuel, string $nouveauMotDePasse): void
    {
        if (! Hash::check($motDePasseActuel, $utilisateur->getAuthPassword())) {
            throw new AuthentificationException('Mot de passe actuel incorrect.', 422);
        }

        $utilisateur->mot_de_passe_hash = Hash::make($nouveauMotDePasse);
        $utilisateur->save();

        $this->journalAudit->enregistrer('utilisateur.changement_mot_de_passe', $utilisateur->id, 'utilisateur', $utilisateur->id);
    }

    private function enregistrerEchec(Utilisateur $utilisateur): void
    {
        $utilisateur->tentatives_echec += 1;
        if ($utilisateur->tentatives_echec >= config('siarn.verrouillage.tentatives_max')) {
            $utilisateur->verrouille_jusqu_a = now()->addMinutes(config('siarn.verrouillage.duree_minutes'));
        }
        $utilisateur->save();
    }

    private function finaliserConnexion(Utilisateur $utilisateur, ?string $ip, ?string $userAgent): array
    {
        $emission = $this->jwtService->emettre($utilisateur->id, 'acces', config('siarn.jwt.ttl_minutes'));

        SessionJwt::create([
            'utilisateur_id' => $utilisateur->id,
            'jti' => $emission['jti'],
            'expire_a' => Carbon::createFromTimestamp($emission['expire_a']),
            'ip_creation' => $ip,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);

        $utilisateur->dernier_login_a = now();
        $utilisateur->save();

        $this->journaliserConnexion($utilisateur->id, $utilisateur->email, true, null, $ip, $userAgent);
        $this->journalAudit->enregistrer('utilisateur.connexion', $utilisateur->id, 'utilisateur', $utilisateur->id);

        return ['statut' => 'connecte', 'token' => $emission['token'], 'utilisateur' => $utilisateur];
    }

    private function uriProvisionnement(string $email, string $secretEnClair): string
    {
        $emetteur = rawurlencode(config('siarn.mfa.emetteur'));
        $label = rawurlencode($email);

        return "otpauth://totp/{$emetteur}:{$label}?secret={$secretEnClair}&issuer={$emetteur}&algorithm=SHA1&digits=6&period=30";
    }

    private function journaliserConnexion(?string $utilisateurId, string $email, bool $succes, ?string $motifEchec, ?string $ip, ?string $userAgent): void
    {
        DB::table('journal_connexions')->insert([
            'id' => (string) Str::uuid(),
            'utilisateur_id' => $utilisateurId,
            'email_tentative' => $email,
            'succes' => $succes,
            'motif_echec' => $motifEchec,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'date_heure' => now(),
        ]);
    }

}
