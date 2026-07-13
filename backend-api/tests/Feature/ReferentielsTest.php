<?php

namespace Tests\Feature;

use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\SessionJwt;
use App\Models\Utilisateur;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Couvre §7.2 du PRD : filieres/modules/etudiants, chef_departement_id
// nullable (§4), RBAC en lecture/ecriture, import de listes.
class ReferentielsTest extends TestCase
{
    use DatabaseTransactions;

    private function creerUtilisateur(string $role, bool $statutMfa = true): Utilisateur
    {
        static $compteur = 0;
        $compteur++;

        return Utilisateur::create([
            'nom' => 'Test',
            'prenom' => 'Utilisateur',
            'email' => "{$role}{$compteur}@siarn.test",
            'mot_de_passe_hash' => Hash::make('MotDePasse123!'),
            'role' => $role,
            'statut_mfa' => $statutMfa,
            'actif' => true,
        ]);
    }

    /**
     * Emet directement un token d'acces valide (session_jwt incluse), sans
     * repasser par le flux complet de connexion/MFA deja couvert par
     * AuthentificationTest - ici on teste le RBAC des referentiels, pas le login.
     */
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

    private function creerFiliere(?string $chefDepartementId = null): Filiere
    {
        return Filiere::create([
            'nom' => 'Genie Logiciel',
            'code' => 'GL-'.uniqid(),
            'chef_departement_id' => $chefDepartementId,
            'actif' => true,
        ]);
    }

    public function test_agent_scolarite_peut_creer_une_filiere_avec_chef_departement_nullable(): void
    {
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/filieres', ['nom' => 'Genie Logiciel', 'code' => 'GL01']);

        $reponse->assertCreated()->assertJsonPath('chef_departement', null);
    }

    public function test_chef_departement_id_doit_referencer_un_role_valide(): void
    {
        $agent = $this->creerUtilisateur('agent_scolarite');
        $enseignant = $this->creerUtilisateur('enseignant'); // mauvais role pour ce champ
        $token = $this->jetonPour($agent);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/filieres', [
                'nom' => 'Genie Logiciel',
                'code' => 'GL02',
                'chef_departement_id' => $enseignant->id,
            ])
            ->assertStatus(422);
    }

    public function test_chef_departement_id_accepte_un_responsable_academique_cumulant_les_roles(): void
    {
        $agent = $this->creerUtilisateur('agent_scolarite');
        $responsable = $this->creerUtilisateur('responsable_academique');
        $token = $this->jetonPour($agent);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/filieres', [
                'nom' => 'Genie Logiciel',
                'code' => 'GL03',
                'chef_departement_id' => $responsable->id,
            ])
            ->assertCreated()
            ->assertJsonPath('chef_departement.role', 'responsable_academique');
    }

    public function test_enseignant_ne_peut_pas_creer_de_filiere(): void
    {
        $enseignant = $this->creerUtilisateur('enseignant');
        $token = $this->jetonPour($enseignant);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/filieres', ['nom' => 'Genie Logiciel', 'code' => 'GL04'])
            ->assertStatus(403);
    }

    public function test_directeur_peut_lire_mais_pas_ecrire_les_filieres(): void
    {
        $this->creerFiliere();
        $directeur = $this->creerUtilisateur('directeur');
        $token = $this->jetonPour($directeur);

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/filieres')->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/filieres', ['nom' => 'X', 'code' => 'X01'])
            ->assertStatus(403);
    }

    public function test_recherche_multicritere_etudiants(): void
    {
        $filiere = $this->creerFiliere();
        Etudiant::create(['matricule' => 'MAT001', 'nom' => 'Kabore', 'prenom' => 'Awa', 'filiere_id' => $filiere->id, 'niveau' => 'L1', 'annee_academique' => '2025-2026', 'actif' => true]);
        Etudiant::create(['matricule' => 'MAT002', 'nom' => 'Sawadogo', 'prenom' => 'Issa', 'filiere_id' => $filiere->id, 'niveau' => 'L2', 'annee_academique' => '2025-2026', 'actif' => true]);

        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/etudiants?q=Kabore');

        $reponse->assertOk();
        $this->assertCount(1, $reponse->json('donnees'));
        $this->assertEquals('MAT001', $reponse->json('donnees.0.matricule'));
    }

    public function test_import_csv_etudiants(): void
    {
        $filiere = $this->creerFiliere();
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $csv = "matricule,nom,prenom,filiere_code,niveau,annee_academique\n"
            ."MAT100,Ouedraogo,Fatim,{$filiere->code},L1,2025-2026\n"
            ."MAT101,Traore,Boubacar,CODE_INEXISTANT,L1,2025-2026\n";

        $fichier = UploadedFile::fake()->createWithContent('etudiants.csv', $csv);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/etudiants/import', ['fichier' => $fichier], ['Accept' => 'application/json']);

        $reponse->assertOk();
        $this->assertEquals(1, $reponse->json('crees'));
        $this->assertCount(1, $reponse->json('erreurs'));
        $this->assertDatabaseHas('etudiants', ['matricule' => 'MAT100']);
        $this->assertDatabaseMissing('etudiants', ['matricule' => 'MAT101']);
    }

    public function test_import_csv_avec_bom_utf8_excel(): void
    {
        // Regression : un CSV exporte depuis Excel (format courant pour les
        // agents de scolarite, §14) demarre par un BOM UTF-8, qui cassait
        // auparavant la cle du premier en-tete ("matricule").
        $filiere = $this->creerFiliere();
        $agent = $this->creerUtilisateur('agent_scolarite');
        $token = $this->jetonPour($agent);

        $csv = "\xEF\xBB\xBF"."matricule,nom,prenom,filiere_code,niveau,annee_academique\n"
            ."MAT200,Zongo,Rasmane,{$filiere->code},L1,2025-2026\n";

        $fichier = UploadedFile::fake()->createWithContent('etudiants.csv', $csv);

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/etudiants/import', ['fichier' => $fichier], ['Accept' => 'application/json']);

        $reponse->assertOk();
        $this->assertEquals(1, $reponse->json('crees'));
        $this->assertCount(0, $reponse->json('erreurs'));
        $this->assertDatabaseHas('etudiants', ['matricule' => 'MAT200']);
    }
}
