<?php

namespace Tests\Feature;

use App\Models\DocumentCorpus;
use App\Models\SessionJwt;
use App\Models\Utilisateur;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Couvre §8.1/§10 du PRD : constitution/annotation du corpus OCR (E4),
// isolation du disque 'corpus' (jamais melange aux PV de production), double
// annotation, split train/val/test sans chevauchement, RBAC (§5 : Agent
// scolarite + Admin uniquement).
class CorpusTest extends TestCase
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

    public function test_agent_scolarite_peut_importer_un_document_corpus(): void
    {
        Storage::fake('corpus');
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->post('/api/corpus/documents', [
            'fichier' => UploadedFile::fake()->create('doc1.jpg', 100, 'image/jpeg'),
            'type_gabarit' => 'defaut',
        ], ['Accept' => 'application/json']);

        $reponse->assertCreated();
        $this->assertDatabaseHas('documents_corpus', ['nom_fichier' => 'doc1.jpg', 'anonymise' => true]);
    }

    public function test_enseignant_ne_peut_pas_importer_de_document_corpus(): void
    {
        $enseignant = $this->creerUtilisateur('enseignant');
        $token = $this->jetonPour($enseignant);

        $this->withHeader('Authorization', "Bearer {$token}")->post('/api/corpus/documents', [
            'fichier' => UploadedFile::fake()->create('doc1.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertStatus(403);
    }

    public function test_double_annotation_meme_champ_ordres_distincts(): void
    {
        Storage::fake('corpus');
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $document = DocumentCorpus::create([
            'nom_fichier' => 'doc.jpg', 'chemin_fichier' => 'documents/doc.jpg',
            'type_gabarit' => 'defaut', 'anonymise' => true, 'created_at' => now(),
        ]);

        $coord = ['x' => 10, 'y' => 20, 'largeur' => 100, 'hauteur' => 30];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/corpus/documents/{$document->id}/annotations", [
                'champ' => 'matricule', 'valeur_verite_terrain' => 'MAT001', 'coordonnees_zone' => $coord, 'ordre_annotation' => 1,
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/corpus/documents/{$document->id}/annotations", [
                'champ' => 'matricule', 'valeur_verite_terrain' => 'MAT001', 'coordonnees_zone' => $coord, 'ordre_annotation' => 2,
            ])->assertCreated();

        // Meme ordre deux fois -> rejete
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/corpus/documents/{$document->id}/annotations", [
                'champ' => 'matricule', 'valeur_verite_terrain' => 'MAT001X', 'coordonnees_zone' => $coord, 'ordre_annotation' => 1,
            ])->assertStatus(422);

        $this->assertDatabaseCount('annotations', 2);
    }

    public function test_repartition_train_val_test_sans_chevauchement(): void
    {
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        for ($i = 0; $i < 20; $i++) {
            DocumentCorpus::create([
                'nom_fichier' => "doc{$i}.jpg", 'chemin_fichier' => "documents/doc{$i}.jpg",
                'type_gabarit' => 'defaut', 'anonymise' => true, 'created_at' => now(),
            ]);
        }

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/corpus/repartir');

        $reponse->assertOk();
        $this->assertEquals(20, $reponse->json('repartis'));
        $this->assertEquals(20, $reponse->json('train') + $reponse->json('val') + $reponse->json('test'));

        // Aucun chevauchement possible par construction (colonne jeu unique par ligne),
        // mais on verifie qu'aucun document ne reste sans jeu assigne.
        $this->assertEquals(0, DocumentCorpus::whereNull('jeu')->count());
    }
}
