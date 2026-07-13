<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\SessionJwt;
use App\Models\Utilisateur;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Couvre §7.3/§9.1 du PRD : import de lots de PV, machine a etats explicite
// (soumis -> en_traitement -> erreur_extraction en cas d'echec pretraitement),
// RBAC (import reserve a agent_scolarite, meme Admin n'y a pas droit, §5).
//
// ocr-service est simule via Http::fake() : la logique OpenCV reelle est
// couverte par la suite pytest d'ocr-service, pas par ce test backend-api.
class PvTest extends TestCase
{
    use DatabaseTransactions;

    private function creerUtilisateur(string $role): Utilisateur
    {
        static $compteur = 0;
        $compteur++;

        return Utilisateur::create([
            'nom' => 'Test',
            'prenom' => 'Utilisateur',
            'email' => "{$role}{$compteur}@siarn.test",
            'mot_de_passe_hash' => Hash::make('MotDePasse123!'),
            'role' => $role,
            'statut_mfa' => true,
            'actif' => true,
        ]);
    }

    private function jetonPour(Utilisateur $utilisateur): string
    {
        $jwt = app(JwtService::class);
        $emission = $jwt->emettre($utilisateur->id, 'acces', 60);

        SessionJwt::create([
            'utilisateur_id' => $utilisateur->id,
            'jti' => $emission['jti'],
            'expire_a' => now()->addHour(),
            'created_at' => now(),
        ]);

        return $emission['token'];
    }

    private function creerFiliere(): Filiere
    {
        return Filiere::create(['nom' => 'Genie Logiciel', 'code' => 'GL-'.uniqid(), 'actif' => true]);
    }

    public function test_import_reussi_transitionne_vers_en_traitement_avec_zones(): void
    {
        Storage::fake('pv');
        Http::fake([
            '*/pretraitement' => Http::response([
                'type_gabarit' => 'defaut',
                'zones' => [['nom' => 'en_tete', 'x' => 0, 'y' => 0, 'largeur' => 100, 'hauteur' => 50]],
                'image_pretraitee_base64' => base64_encode('donnees-image-simulees'),
            ], 200),
        ]);

        $filiere = $this->creerFiliere();
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->post('/api/pv/import', [
            'fichiers' => [UploadedFile::fake()->create('pv1.jpg', 100, 'image/jpeg')],
            'code_matiere' => 'INF301',
            'filiere_id' => $filiere->id,
            'semestre' => 'S5',
            'annee_academique' => '2025-2026',
        ], ['Accept' => 'application/json']);

        $reponse->assertCreated();
        $pv = $reponse->json('pv_importes.0');
        $this->assertEquals('en_traitement', $pv['statut']);
        $this->assertCount(1, $pv['zones_segmentees']);

        $this->assertDatabaseHas('historique_transitions_pv', [
            'pv_id' => $pv['id'], 'nouveau_statut' => 'soumis',
        ]);
        $this->assertDatabaseHas('historique_transitions_pv', [
            'pv_id' => $pv['id'], 'ancien_statut' => 'soumis', 'nouveau_statut' => 'en_traitement',
        ]);
    }

    public function test_echec_pretraitement_transitionne_vers_erreur_extraction(): void
    {
        Storage::fake('pv');
        Http::fake(['*/pretraitement' => Http::response(['detail' => 'erreur'], 500)]);

        $filiere = $this->creerFiliere();
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->post('/api/pv/import', [
            'fichiers' => [UploadedFile::fake()->create('pv1.jpg', 100, 'image/jpeg')],
            'code_matiere' => 'INF301',
            'filiere_id' => $filiere->id,
            'semestre' => 'S5',
            'annee_academique' => '2025-2026',
        ], ['Accept' => 'application/json']);

        $reponse->assertCreated();
        $this->assertEquals('erreur_extraction', $reponse->json('pv_importes.0.statut'));

        $this->assertDatabaseHas('historique_transitions_pv', [
            'pv_id' => $reponse->json('pv_importes.0.id'),
            'ancien_statut' => 'en_traitement',
            'nouveau_statut' => 'erreur_extraction',
        ]);
    }

    public function test_enseignant_ne_peut_pas_importer_de_pv(): void
    {
        $filiere = $this->creerFiliere();
        $enseignant = $this->creerUtilisateur('enseignant');
        $token = $this->jetonPour($enseignant);

        $this->withHeader('Authorization', "Bearer {$token}")->post('/api/pv/import', [
            'fichiers' => [UploadedFile::fake()->create('pv1.jpg', 100, 'image/jpeg')],
            'code_matiere' => 'INF301',
            'filiere_id' => $filiere->id,
            'semestre' => 'S5',
            'annee_academique' => '2025-2026',
        ], ['Accept' => 'application/json'])->assertStatus(403);
    }

    public function test_admin_ne_peut_pas_importer_de_pv(): void
    {
        // §5 RBAC : la ligne "Importer/numeriser un PV" n'a QUE l'agent de
        // scolarite coche, meme Admin est explicitement exclu.
        $filiere = $this->creerFiliere();
        $admin = $this->creerUtilisateur('admin');
        $token = $this->jetonPour($admin);

        $this->withHeader('Authorization', "Bearer {$token}")->post('/api/pv/import', [
            'fichiers' => [UploadedFile::fake()->create('pv1.jpg', 100, 'image/jpeg')],
            'code_matiere' => 'INF301',
            'filiere_id' => $filiere->id,
            'semestre' => 'S5',
            'annee_academique' => '2025-2026',
        ], ['Accept' => 'application/json'])->assertStatus(403);
    }

    public function test_directeur_peut_lister_les_pv(): void
    {
        $directeur = $this->creerUtilisateur('directeur');
        $token = $this->jetonPour($directeur);

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/pv')->assertOk();
    }
}
