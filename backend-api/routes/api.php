<?php

use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\MfaController;
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
});
