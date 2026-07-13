<?php

namespace Tests\Feature;

use App\Models\Absence;
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

// Couvre §7.6/§9.1 du PRD (E8) : saisie des notes, penalites automatiques
// (fraude/absence), validation hierarchique (chef de departement scope a sa
// filiere, responsable academique aux 3), calcul de moyenne, SLA.
class WorkflowTest extends TestCase
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

    private function creerFiliere(?string $chefId = null): Filiere
    {
        return Filiere::create(['nom' => 'Genie Logiciel', 'code' => 'GL-'.uniqid(), 'chef_departement_id' => $chefId, 'actif' => true]);
    }

    private function creerModule(Filiere $filiere, ?string $enseignantId = null): Module
    {
        return Module::create([
            'code' => 'INF-'.uniqid(), 'nom' => 'Bases de donnees', 'filiere_id' => $filiere->id,
            'niveau' => 'L3', 'semestre' => 'S5', 'coefficient' => 3, 'credits' => 5, 'actif' => true,
            'enseignant_id' => $enseignantId,
        ]);
    }

    private function creerEtudiant(Filiere $filiere): Etudiant
    {
        return Etudiant::create([
            'matricule' => 'MAT-'.uniqid(), 'nom' => 'Kabore', 'prenom' => 'Awa', 'filiere_id' => $filiere->id,
            'niveau' => 'L3', 'annee_academique' => '2025-2026', 'actif' => true,
        ]);
    }

    private function creerPv(Filiere $filiere, Module $module, string $statut, ?string $deposeParId = null): ProcesVerbal
    {
        return ProcesVerbal::create([
            'nom_fichier' => 'pv.jpg', 'chemin_fichier' => 'originaux/pv.jpg', 'code_matiere' => $module->code,
            'module_id' => $module->id, 'filiere_id' => $filiere->id, 'semestre' => 'S5', 'annee_academique' => '2025-2026',
            'statut' => $statut, 'depose_par_id' => $deposeParId ?? $this->creerUtilisateur('agent_scolarite')->id,
        ]);
    }

    public function test_agent_peut_saisir_une_note_pendant_verification(): void
    {
        $filiere = $this->creerFiliere();
        $module = $this->creerModule($filiere);
        $etudiant = $this->creerEtudiant($filiere);
        $pv = $this->creerPv($filiere, $module, 'en_verification');

        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/pv/{$pv->id}/notes", [
            'etudiant_id' => $etudiant->id, 'valeur' => 15.5,
        ]);

        $reponse->assertCreated();
        $this->assertDatabaseHas('notes', ['pv_id' => $pv->id, 'etudiant_id' => $etudiant->id, 'valeur' => 15.5]);
    }

    public function test_absence_declenche_penalite_automatique_quand_seuil_atteint(): void
    {
        $filiere = $this->creerFiliere();
        $module = $this->creerModule($filiere);
        $etudiant = $this->creerEtudiant($filiere);
        $pv = $this->creerPv($filiere, $module, 'en_verification');
        $note = Note::create(['etudiant_id' => $etudiant->id, 'pv_id' => $pv->id, 'valeur' => 14, 'coefficient' => 3, 'credit' => 5, 'etat_validation' => 'corrige']);

        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        // Seuil par defaut = 8h (config siarn.penalite.seuil_absence_heures)
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/absences', [
            'etudiant_id' => $etudiant->id, 'module_id' => $module->id, 'duree_heures' => 5, 'date' => '2026-01-10', 'justifiee' => false,
        ])->assertCreated();

        $note->refresh();
        $this->assertNull($note->motif_penalite); // pas encore au seuil

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/absences', [
            'etudiant_id' => $etudiant->id, 'module_id' => $module->id, 'duree_heures' => 4, 'date' => '2026-01-17', 'justifiee' => false,
        ])->assertCreated();

        $note->refresh();
        $this->assertEquals(0, $note->valeur);
        $this->assertEquals('absence_non_justifiee', $note->motif_penalite);
    }

    public function test_enseignant_peut_signaler_fraude_sur_son_module(): void
    {
        $enseignant = $this->creerUtilisateur('enseignant');
        $filiere = $this->creerFiliere();
        $module = $this->creerModule($filiere, $enseignant->id);
        $etudiant = $this->creerEtudiant($filiere);
        $pv = $this->creerPv($filiere, $module, 'en_validation');
        $note = Note::create(['etudiant_id' => $etudiant->id, 'pv_id' => $pv->id, 'valeur' => 16, 'coefficient' => 3, 'credit' => 5, 'etat_validation' => 'corrige']);

        $token = $this->jetonPour($enseignant);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/notes/{$note->id}/signaler-fraude", [
            'motif' => 'Ecriture non conforme, suspicion de substitution de copie.',
        ]);

        $reponse->assertOk();
        $note->refresh();
        $this->assertEquals(0, $note->valeur);
        $this->assertEquals('fraude', $note->motif_penalite);
    }

    public function test_enseignant_ne_peut_pas_signaler_fraude_hors_son_module(): void
    {
        $enseignantA = $this->creerUtilisateur('enseignant');
        $enseignantB = $this->creerUtilisateur('enseignant');
        $filiere = $this->creerFiliere();
        $module = $this->creerModule($filiere, $enseignantA->id);
        $etudiant = $this->creerEtudiant($filiere);
        $pv = $this->creerPv($filiere, $module, 'en_validation');
        $note = Note::create(['etudiant_id' => $etudiant->id, 'pv_id' => $pv->id, 'valeur' => 16, 'coefficient' => 3, 'credit' => 5, 'etat_validation' => 'corrige']);

        $token = $this->jetonPour($enseignantB);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/notes/{$note->id}/signaler-fraude", [
            'motif' => 'Tentative non autorisee.',
        ])->assertStatus(403);
    }

    public function test_chef_departement_peut_valider_sa_filiere_et_integre_le_pv(): void
    {
        $chef = $this->creerUtilisateur('chef_departement');
        $filiere = $this->creerFiliere($chef->id);
        $module = $this->creerModule($filiere);
        $etudiant = $this->creerEtudiant($filiere);
        $pv = $this->creerPv($filiere, $module, 'en_validation');
        Note::create(['etudiant_id' => $etudiant->id, 'pv_id' => $pv->id, 'valeur' => 15, 'coefficient' => 3, 'credit' => 5, 'etat_validation' => 'corrige']);

        $token = $this->jetonPour($chef);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/pv/{$pv->id}/valider", [
            'decision' => 'valider',
        ]);

        $reponse->assertOk();
        $this->assertEquals('integre', $reponse->json('statut'));
        $this->assertDatabaseHas('notes', ['pv_id' => $pv->id, 'etat_validation' => 'valide']);
        $this->assertDatabaseHas('decisions', ['pv_id' => $pv->id, 'type_decision' => 'valider']);
    }

    public function test_chef_departement_ne_peut_pas_valider_une_autre_filiere(): void
    {
        $chef = $this->creerUtilisateur('chef_departement');
        $autreFiliere = $this->creerFiliere(); // pas chef de celle-ci
        $module = $this->creerModule($autreFiliere);
        $pv = $this->creerPv($autreFiliere, $module, 'en_validation');

        $token = $this->jetonPour($chef);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/pv/{$pv->id}/valider", [
            'decision' => 'valider',
        ])->assertStatus(403);
    }

    public function test_responsable_academique_peut_valider_nimporte_quelle_filiere(): void
    {
        $responsable = $this->creerUtilisateur('responsable_academique');
        $filiere = $this->creerFiliere(); // chef_departement_id NULL : cumule par le responsable (§4)
        $module = $this->creerModule($filiere);
        $pv = $this->creerPv($filiere, $module, 'en_validation');

        $token = $this->jetonPour($responsable);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/pv/{$pv->id}/valider", [
            'decision' => 'valider',
        ])->assertOk();
    }

    public function test_rejet_necessite_un_motif(): void
    {
        $responsable = $this->creerUtilisateur('responsable_academique');
        $filiere = $this->creerFiliere();
        $module = $this->creerModule($filiere);
        $pv = $this->creerPv($filiere, $module, 'en_validation');

        $token = $this->jetonPour($responsable);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/pv/{$pv->id}/valider", [
            'decision' => 'rejeter',
        ])->assertStatus(422);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/pv/{$pv->id}/valider", [
            'decision' => 'rejeter', 'motif' => 'Incoherence entre le PV papier et les notes saisies.',
        ]);
        $reponse->assertOk();
        $this->assertEquals('rejete', $reponse->json('statut'));
    }

    public function test_calcul_moyenne_ponderee(): void
    {
        $filiere = $this->creerFiliere();
        $moduleA = $this->creerModule($filiere);
        $moduleB = $this->creerModule($filiere);
        $etudiant = $this->creerEtudiant($filiere);
        $pvA = $this->creerPv($filiere, $moduleA, 'integre');
        $pvB = $this->creerPv($filiere, $moduleB, 'integre');

        Note::create(['etudiant_id' => $etudiant->id, 'pv_id' => $pvA->id, 'valeur' => 16, 'coefficient' => 2, 'credit' => 4, 'etat_validation' => 'valide']);
        Note::create(['etudiant_id' => $etudiant->id, 'pv_id' => $pvB->id, 'valeur' => 10, 'coefficient' => 1, 'credit' => 2, 'etat_validation' => 'valide']);

        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/etudiants/{$etudiant->id}/moyenne?semestre=S5&annee_academique=2025-2026");

        $reponse->assertOk();
        // (16*2 + 10*1) / 3 = 14
        $this->assertEquals(14.0, $reponse->json('moyenne'));
    }

    public function test_commande_sla_cree_une_alerte_pour_pv_bloque(): void
    {
        $filiere = $this->creerFiliere();
        $module = $this->creerModule($filiere);
        $pv = $this->creerPv($filiere, $module, 'en_verification');
        $pv->transitions()->create([
            'ancien_statut' => 'en_traitement', 'nouveau_statut' => 'en_verification',
            'date_heure' => now()->subDays(10),
        ]);

        $this->artisan('siarn:verifier-sla')->assertExitCode(0);

        $this->assertDatabaseHas('alertes', ['pv_id' => $pv->id, 'niveau' => 'avertissement']);
    }
}
