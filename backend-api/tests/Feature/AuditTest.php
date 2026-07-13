<?php

namespace Tests\Feature;

use App\Models\SessionJwt;
use App\Models\Utilisateur;
use App\Services\Audit\JournalAuditService;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Couvre §7.9/§13.5/UC-10 du PRD (E11) : consultation de la piste d'audit
// (Admin + Directeur uniquement, §5), en-tetes de securite globaux.
class AuditTest extends TestCase
{
    use DatabaseTransactions;

    private function creerUtilisateur(string $role): Utilisateur
    {
        static $compteur = 0;
        $compteur++;

        return Utilisateur::create([
            'nom' => 'Test', 'prenom' => 'Utilisateur', 'email' => "{$role}{$compteur}@siarn.test",
            'mot_de_passe_hash' => Hash::make('MotDePasse123!'), 'role' => $role,
            'statut_mfa' => true, 'actif' => true,
        ]);
    }

    private function jetonPour(Utilisateur $utilisateur): string
    {
        $jwt = app(JwtService::class);
        $emission = $jwt->emettre($utilisateur->id, 'acces', 60);
        SessionJwt::create([
            'utilisateur_id' => $utilisateur->id, 'jti' => $emission['jti'],
            'expire_a' => now()->addHour(), 'created_at' => now(),
        ]);

        return $emission['token'];
    }

    public function test_directeur_peut_consulter_le_journal_audit(): void
    {
        $directeur = $this->creerUtilisateur('directeur');
        app(JournalAuditService::class)->enregistrer('test.action', $directeur->id, 'test', $directeur->id, ['x' => 1]);

        $token = $this->jetonPour($directeur);
        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/audit');

        $reponse->assertOk();
        $this->assertGreaterThan(0, $reponse->json('total'));
    }

    public function test_agent_scolarite_ne_peut_pas_consulter_le_journal_audit(): void
    {
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/audit')->assertStatus(403);
    }

    public function test_reponses_api_incluent_les_en_tetes_de_securite(): void
    {
        $reponse = $this->getJson('/up');

        $reponse->assertHeader('X-Content-Type-Options', 'nosniff');
        $reponse->assertHeader('X-Frame-Options', 'DENY');
    }
}
