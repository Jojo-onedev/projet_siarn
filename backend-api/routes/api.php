<?php

use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Referentiels\EtudiantController;
use App\Http\Controllers\Referentiels\FiliereController;
use App\Http\Controllers\Referentiels\ModuleController;
use App\Http\Controllers\Pv\PvController;
use App\Http\Controllers\Corpus\CorpusController;
use App\Http\Controllers\Ocr\ModeleOcrController;
use Illuminate\Support\Facades\Route;

// §7.1 Gestion des utilisateurs et des acces (E1). Routes publiques minimales
// (connexion), le reste exige un JWT valide (auth:api).
Route::post('/auth/connexion', [AuthController::class, 'connexion']);
Route::post('/auth/mfa/verifier', [AuthController::class, 'verifierMfa']);

Route::middleware('auth:api')->group(function () {
    Route::post('/auth/deconnexion', [AuthController::class, 'deconnexion']);
    Route::get('/auth/moi', [AuthController::class, 'moi']);

    // Enrolement MFA : accessible meme si le MFA n'est pas encore configure
    // (c'est justement le but), donc PAS derriere 'mfa.requise'.
    Route::post('/auth/mfa/activer', [MfaController::class, 'activer']);
    Route::post('/auth/mfa/confirmer', [MfaController::class, 'confirmer']);

    // Routes metier (E2+) : passeront systematiquement par 'mfa.requise' en
    // plus de 'auth:api', pour bloquer les roles a privileges eleves tant que
    // leur MFA n'est pas actif (§13.1). Demonstre ici avec la gestion des comptes.
    Route::middleware(['mfa.requise', 'role:admin'])->group(function () {
        Route::get('/utilisateurs', [UtilisateurController::class, 'index']);
        Route::post('/utilisateurs', [UtilisateurController::class, 'store']);
    });

    // §7.2 Referentiels (E2). Lecture large (pilotage/tableaux de bord des
    // §5), ecriture reservee a l'agent de scolarite et l'admin (saisie
    // operationnelle du referentiel).
    Route::middleware('mfa.requise')->group(function () {
        $lectureReferentiels = 'role:agent_scolarite,chef_departement,responsable_academique,directeur,admin';
        $ecritureReferentiels = 'role:agent_scolarite,admin';

        Route::middleware($lectureReferentiels)->group(function () {
            Route::get('/filieres', [FiliereController::class, 'index']);
            Route::get('/filieres/{filiere}', [FiliereController::class, 'show']);
            Route::get('/modules', [ModuleController::class, 'index']);
            Route::get('/etudiants', [EtudiantController::class, 'index']);
            Route::get('/etudiants/{etudiant}', [EtudiantController::class, 'show']);
        });

        Route::middleware($ecritureReferentiels)->group(function () {
            Route::post('/filieres', [FiliereController::class, 'store']);
            Route::put('/filieres/{filiere}', [FiliereController::class, 'update']);
            Route::post('/modules', [ModuleController::class, 'store']);
            Route::put('/modules/{module}', [ModuleController::class, 'update']);
            Route::post('/etudiants', [EtudiantController::class, 'store']);
            Route::put('/etudiants/{etudiant}', [EtudiantController::class, 'update']);
            Route::post('/etudiants/import', [EtudiantController::class, 'importer']);
        });

        // §7.3 Import & pretraitement des PV (E3). Import reserve a l'agent
        // de scolarite (§5 RBAC : seul role coche pour "Importer/numeriser un
        // PV", pas meme Admin) ; lecture ouverte comme les autres referentiels
        // pour le pilotage (§7.8, futurs tableaux de bord).
        Route::middleware('role:agent_scolarite,chef_departement,responsable_academique,directeur,admin')->group(function () {
            Route::get('/pv', [PvController::class, 'index']);
            Route::get('/pv/{pv}', [PvController::class, 'show']);
        });

        Route::middleware('role:agent_scolarite')->group(function () {
            Route::post('/pv/import', [PvController::class, 'importer']);
            // §7.5, E7 : "Corriger donnees OCR" -> Agent scolarite uniquement (§5).
            Route::post('/pv/{pv}/verifier', [PvController::class, 'verifier']);
        });

        // §7.8, §8.3 : consultation des versions du modele OCR (Admin, §5 :
        // "Entrainer/evaluer le modele OCR" -> Admin (dev)).
        Route::middleware('role:admin')->group(function () {
            Route::get('/modeles-ocr', [ModeleOcrController::class, 'index']);
        });

        // §8.1, E4 : constitution/annotation du corpus OCR. §5 RBAC :
        // "Constituer/annoter corpus OCR" -> Agent scolarite + Admin uniquement.
        Route::middleware('role:agent_scolarite,admin')->group(function () {
            Route::get('/corpus/documents', [CorpusController::class, 'index']);
            Route::get('/corpus/documents/{document}', [CorpusController::class, 'show']);
            Route::post('/corpus/documents', [CorpusController::class, 'store']);
            Route::post('/corpus/documents/{document}/annotations', [CorpusController::class, 'storeAnnotation']);
            Route::post('/corpus/repartir', [CorpusController::class, 'repartir']);
        });
    });
});
