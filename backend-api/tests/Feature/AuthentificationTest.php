<?php

namespace Tests\Feature;

use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

// Couvre §7.1/§13.1 du PRD : connexion, verrouillage anti brute-force, RBAC,
// MFA obligatoire pour les roles a privileges eleves, revocation de session.
//
// DatabaseTransactions (pas RefreshDatabase) : le schema utilise des types
// Postgres personnalises (enums, cf. 0001_extensions_types.sql) que
// `migrate:fresh` ne sait pas redroper proprement entre deux executions de
// la suite (DROP TABLE seul, les CREATE TYPE echouent au run suivant avec
// "already exists"). Le schema de siarn_test est migre une fois pour toutes
// (php artisan migrate --database=... voir README/commande dev), puis chaque
// test s'execute dans une transaction annulee a la fin.
class AuthentificationTest extends TestCase
{
    use DatabaseTransactions;

    private function creerUtilisateur(string $role, string $motDePasse = 'MotDePasse123!', bool $statutMfa = false): Utilisateur
    {
        return Utilisateur::create([
            'nom' => 'Test',
            'prenom' => 'Utilisateur',
            'email' => $role.'@siarn.test',
            'mot_de_passe_hash' => Hash::make($motDePasse),
            'role' => $role,
            'statut_mfa' => $statutMfa,
            'actif' => true,
        ]);
    }

    public function test_connexion_reussie_sans_mfa(): void
    {
        $utilisateur = $this->creerUtilisateur('enseignant');

        $reponse = $this->postJson('/api/auth/connexion', [
            'email' => $utilisateur->email,
            'mot_de_passe' => 'MotDePasse123!',
        ]);

        $reponse->assertOk()->assertJson(['statut' => 'connecte']);
        $this->assertNotEmpty($reponse->json('token'));

        $this->assertDatabaseHas('journal_connexions', [
            'email_tentative' => $utilisateur->email,
            'succes' => true,
        ]);
    }

    public function test_mot_de_passe_invalide_est_rejete(): void
    {
        $utilisateur = $this->creerUtilisateur('enseignant');

        $reponse = $this->postJson('/api/auth/connexion', [
            'email' => $utilisateur->email,
            'mot_de_passe' => 'FauxMotDePasse',
        ]);

        $reponse->assertStatus(401);
        $this->assertEquals(1, $utilisateur->fresh()->tentatives_echec);
    }

    public function test_verrouillage_apres_tentatives_max(): void
    {
        $utilisateur = $this->creerUtilisateur('enseignant');
        $max = config('siarn.verrouillage.tentatives_max');

        for ($i = 0; $i < $max; $i++) {
            $this->postJson('/api/auth/connexion', [
                'email' => $utilisateur->email,
                'mot_de_passe' => 'FauxMotDePasse',
            ]);
        }

        $this->assertTrue($utilisateur->fresh()->estVerrouille());

        // Meme avec le bon mot de passe, le compte verrouille refuse la connexion.
        $reponse = $this->postJson('/api/auth/connexion', [
            'email' => $utilisateur->email,
            'mot_de_passe' => 'MotDePasse123!',
        ]);
        $reponse->assertStatus(423);
    }

    public function test_role_non_autorise_est_bloque_par_le_middleware(): void
    {
        $utilisateur = $this->creerUtilisateur('enseignant');

        $token = $this->postJson('/api/auth/connexion', [
            'email' => $utilisateur->email,
            'mot_de_passe' => 'MotDePasse123!',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/utilisateurs')
            ->assertStatus(403);
    }

    public function test_mfa_obligatoire_bloque_les_routes_metier_tant_que_non_configure(): void
    {
        $admin = $this->creerUtilisateur('admin', statutMfa: false);

        $token = $this->postJson('/api/auth/connexion', [
            'email' => $admin->email,
            'mot_de_passe' => 'MotDePasse123!',
        ])->json('token');

        // Login reussit (mfaObligatoire=true mais statut_mfa=false -> pas encore de defi MFA),
        // mais la route metier protegée par mfa.requise doit rester bloquee.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/utilisateurs')
            ->assertStatus(403)
            ->assertJson(['code' => 'MFA_REQUIS']);
    }

    public function test_flux_complet_activation_et_connexion_avec_mfa(): void
    {
        $admin = $this->creerUtilisateur('admin', statutMfa: false);
        $google2fa = new Google2FA();

        $token = $this->postJson('/api/auth/connexion', [
            'email' => $admin->email,
            'mot_de_passe' => 'MotDePasse123!',
        ])->json('token');

        $activation = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/mfa/activer')
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('secret', $activation);
        $secret = $activation['secret'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/mfa/confirmer', ['code' => $google2fa->getCurrentOtp($secret)])
            ->assertOk();

        $this->assertTrue($admin->fresh()->statut_mfa);

        // Nouvelle connexion : doit maintenant exiger le second facteur.
        $connexion = $this->postJson('/api/auth/connexion', [
            'email' => $admin->email,
            'mot_de_passe' => 'MotDePasse123!',
        ])->assertOk()->json();

        $this->assertEquals('mfa_requis', $connexion['statut']);

        $final = $this->postJson('/api/auth/mfa/verifier', [
            'mfa_token' => $connexion['mfa_token'],
            'code' => $google2fa->getCurrentOtp($secret),
        ])->assertOk()->json();

        $this->withHeader('Authorization', "Bearer {$final['token']}")
            ->getJson('/api/utilisateurs')
            ->assertOk();
    }

    public function test_deconnexion_revoque_le_token(): void
    {
        $utilisateur = $this->creerUtilisateur('enseignant');

        $token = $this->postJson('/api/auth/connexion', [
            'email' => $utilisateur->email,
            'mot_de_passe' => 'MotDePasse123!',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/deconnexion')
            ->assertOk();

        // Un meme test PHPUnit reutilise la meme instance d'application entre
        // deux appels ; forgetGuards() simule le guard "frais" d'une vraie
        // nouvelle requete HTTP (sinon le JwtGuard garderait l'utilisateur
        // en cache depuis l'appel precedent, ce qui ne reproduit pas la prod).
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/moi')
            ->assertStatus(401);
    }
}
