<?php

namespace Tests\Feature;

use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\Module;
use App\Models\Note;
use App\Models\ProcesVerbal;
use App\Models\SessionJwt;
use App\Models\Utilisateur;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Couvre §7.2/§7.6/UC-06 du PRD (E12) : portail etudiant, chaque etudiant ne
// consulte que ses propres donnees. Seules les notes des PV 'publie' sont
// visibles (0 note publiee sans validation humaine, regle non negociable).
class PortailTest extends TestCase
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

    public function test_etudiant_voit_uniquement_les_notes_des_pv_publies(): void
    {
        $filiere = Filiere::create(['nom' => 'GL', 'code' => 'GL-'.uniqid(), 'actif' => true]);
        $module = Module::create(['code' => 'M-'.uniqid(), 'nom' => 'Module', 'filiere_id' => $filiere->id, 'niveau' => 'L3', 'semestre' => 'S5', 'coefficient' => 1, 'credits' => 1, 'actif' => true]);
        $compte = $this->creerUtilisateur('etudiant');
        $etudiant = Etudiant::create(['matricule' => 'MAT-'.uniqid(), 'nom' => 'Kabore', 'prenom' => 'Awa', 'filiere_id' => $filiere->id, 'niveau' => 'L3', 'annee_academique' => '2025-2026', 'actif' => true, 'utilisateur_id' => $compte->id]);
        $agent = $this->creerUtilisateur('agent_scolarite');

        $pvPublie = ProcesVerbal::create(['nom_fichier' => 'a.jpg', 'chemin_fichier' => 'x', 'code_matiere' => $module->code, 'module_id' => $module->id, 'filiere_id' => $filiere->id, 'semestre' => 'S5', 'annee_academique' => '2025-2026', 'statut' => 'publie', 'depose_par_id' => $agent->id]);
        $pvEnCours = ProcesVerbal::create(['nom_fichier' => 'b.jpg', 'chemin_fichier' => 'x', 'code_matiere' => $module->code, 'module_id' => $module->id, 'filiere_id' => $filiere->id, 'semestre' => 'S5', 'annee_academique' => '2025-2026', 'statut' => 'en_validation', 'depose_par_id' => $agent->id]);

        Note::create(['etudiant_id' => $etudiant->id, 'pv_id' => $pvPublie->id, 'valeur' => 15, 'coefficient' => 1, 'credit' => 1, 'etat_validation' => 'valide']);
        Note::create(['etudiant_id' => $etudiant->id, 'pv_id' => $pvEnCours->id, 'valeur' => 8, 'coefficient' => 1, 'credit' => 1, 'etat_validation' => 'corrige']);

        $token = $this->jetonPour($compte);
        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mes-notes');

        $reponse->assertOk();
        $this->assertCount(1, $reponse->json());
        $this->assertEquals(15, $reponse->json('0.valeur'));
    }

    public function test_etudiant_ne_peut_pas_consulter_le_profil_dun_autre(): void
    {
        // Un compte etudiant sans profil Etudiant associe (utilisateur_id non lie)
        // ne peut recuperer aucune donnee - jamais un id arbitraire accepte en parametre.
        $compteSansProfil = $this->creerUtilisateur('etudiant');
        $token = $this->jetonPour($compteSansProfil);

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mon-profil')->assertStatus(422);
    }

    public function test_role_non_etudiant_ne_peut_pas_acceder_au_portail(): void
    {
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mes-notes')->assertStatus(403);
    }
}
