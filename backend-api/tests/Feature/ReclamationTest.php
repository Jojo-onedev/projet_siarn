<?php

namespace Tests\Feature;

use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\SessionJwt;
use App\Models\Utilisateur;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Couvre §7.7/UC-07 du PRD (E9) : reclamations etudiantes - initiees
// uniquement par l'etudiant proprietaire (§5), traitees par le personnel.
class ReclamationTest extends TestCase
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

    private function creerEtudiantAvecCompte(): array
    {
        $filiere = Filiere::create(['nom' => 'Genie Logiciel', 'code' => 'GL-'.uniqid(), 'actif' => true]);
        $compte = $this->creerUtilisateur('etudiant');
        $etudiant = Etudiant::create([
            'matricule' => 'MAT-'.uniqid(), 'nom' => 'Traore', 'prenom' => 'Issa', 'filiere_id' => $filiere->id,
            'niveau' => 'L3', 'annee_academique' => '2025-2026', 'actif' => true, 'utilisateur_id' => $compte->id,
        ]);

        return [$compte, $etudiant];
    }

    public function test_etudiant_peut_creer_sa_propre_reclamation(): void
    {
        [$compte, $etudiant] = $this->creerEtudiantAvecCompte();
        $token = $this->jetonPour($compte);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/reclamations', [
            'motif' => 'La note affichee ne correspond pas a celle annoncee par mon enseignant.',
        ]);

        $reponse->assertCreated();
        $this->assertDatabaseHas('reclamations', ['etudiant_id' => $etudiant->id, 'statut' => 'ouverte']);
    }

    public function test_agent_scolarite_ne_peut_pas_creer_de_reclamation(): void
    {
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/reclamations', [
            'motif' => 'Test.',
        ])->assertStatus(403);
    }

    public function test_agent_scolarite_peut_repondre_a_une_reclamation(): void
    {
        [$compte, $etudiant] = $this->creerEtudiantAvecCompte();
        $token = $this->jetonPour($compte);
        $creation = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/reclamations', [
            'motif' => 'Erreur suspectee.',
        ]);
        $reclamationId = $creation->json('id');

        $agent = $this->creerUtilisateur('agent_scolarite');
        $tokenAgent = $this->jetonPour($agent);

        // Un meme test PHPUnit reutilise la meme instance d'application entre
        // deux appels authentifies differents ; forgetGuards() simule le
        // guard "frais" d'une vraie nouvelle requete HTTP (cf. E1 AuthentificationTest).
        auth()->forgetGuards();

        $reponse = $this->withHeader('Authorization', "Bearer {$tokenAgent}")
            ->postJson("/api/reclamations/{$reclamationId}/repondre", [
                'statut' => 'resolue', 'reponse' => 'Verification faite, note confirmee correcte.',
            ]);

        $reponse->assertOk();
        $this->assertDatabaseHas('reclamations', ['id' => $reclamationId, 'statut' => 'resolue']);
    }
}
