<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\Module;
use App\Models\ProcesVerbal;
use App\Models\SessionJwt;
use App\Models\Utilisateur;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Couvre §7.8 du PRD (E10) : tableaux de bord, RBAC scope par filiere pour
// le chef de departement (§5 : ni agent_scolarite ni admin n'ont acces ici).
class DashboardTest extends TestCase
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

    public function test_agent_scolarite_ne_peut_pas_acceder_au_dashboard(): void
    {
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/dashboard/pv')->assertStatus(403);
    }

    public function test_directeur_peut_consulter_le_dashboard_pv(): void
    {
        $directeur = $this->creerUtilisateur('directeur');
        $token = $this->jetonPour($directeur);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/dashboard/pv');

        $reponse->assertOk();
        $this->assertArrayHasKey('total_pv', $reponse->json());
        $this->assertArrayHasKey('par_statut', $reponse->json());
    }

    public function test_chef_departement_est_restreint_a_sa_propre_filiere(): void
    {
        $chef = $this->creerUtilisateur('chef_departement');
        $saFiliere = Filiere::create(['nom' => 'Sa filiere', 'code' => 'SF-'.uniqid(), 'chef_departement_id' => $chef->id, 'actif' => true]);
        $autreFiliere = Filiere::create(['nom' => 'Autre filiere', 'code' => 'AF-'.uniqid(), 'actif' => true]);

        $agent = $this->creerUtilisateur('agent_scolarite');
        $moduleA = Module::create(['code' => 'A-'.uniqid(), 'nom' => 'Module A', 'filiere_id' => $saFiliere->id, 'niveau' => 'L3', 'semestre' => 'S5', 'coefficient' => 1, 'credits' => 1, 'actif' => true]);
        $moduleB = Module::create(['code' => 'B-'.uniqid(), 'nom' => 'Module B', 'filiere_id' => $autreFiliere->id, 'niveau' => 'L3', 'semestre' => 'S5', 'coefficient' => 1, 'credits' => 1, 'actif' => true]);

        ProcesVerbal::create([
            'nom_fichier' => 'pv_a.jpg', 'chemin_fichier' => 'x', 'code_matiere' => $moduleA->code, 'module_id' => $moduleA->id,
            'filiere_id' => $saFiliere->id, 'semestre' => 'S5', 'annee_academique' => '2025-2026', 'statut' => 'soumis', 'depose_par_id' => $agent->id,
        ]);
        ProcesVerbal::create([
            'nom_fichier' => 'pv_b.jpg', 'chemin_fichier' => 'x', 'code_matiere' => $moduleB->code, 'module_id' => $moduleB->id,
            'filiere_id' => $autreFiliere->id, 'semestre' => 'S5', 'annee_academique' => '2025-2026', 'statut' => 'soumis', 'depose_par_id' => $agent->id,
        ]);

        $token = $this->jetonPour($chef);
        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/dashboard/pv');

        $reponse->assertOk();
        $this->assertEquals(1, $reponse->json('total_pv')); // uniquement le PV de sa filiere
    }

    public function test_export_csv_pv(): void
    {
        $directeur = $this->creerUtilisateur('directeur');
        $token = $this->jetonPour($directeur);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->get('/api/dashboard/pv/export');

        $reponse->assertOk();
        $reponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('nom_fichier', $reponse->getContent());
    }
}
