<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// Gestion des comptes/roles (§5 RBAC : reserve a Admin ; §7.1). Le
// provisionnement (creation de compte) reste manuel/admin en V1 : pas de flux
// d'inscription self-service (hors perimetre RBAC pour les roles internes).
class UtilisateurController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Utilisateur::orderBy('nom')->get()->map(fn (Utilisateur $u) => $this->presenter($u))
        );
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'prenom' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:utilisateurs,email'],
            'mot_de_passe' => ['required', 'string', 'min:12'],
            'role' => ['required', Rule::in(array_column(RoleUtilisateur::cases(), 'value'))],
        ]);

        $utilisateur = Utilisateur::create([
            'nom' => $donnees['nom'],
            'prenom' => $donnees['prenom'],
            'email' => $donnees['email'],
            'mot_de_passe_hash' => Hash::make($donnees['mot_de_passe']),
            'role' => $donnees['role'],
            'statut_mfa' => false,
            'actif' => true,
        ]);

        DB::table('journal_audit')->insert([
            'id' => (string) Str::uuid(),
            'action' => 'utilisateur.creation',
            'acteur_id' => $request->user()->id,
            'cible_type' => 'utilisateur',
            'cible_id' => $utilisateur->id,
            'details_json' => json_encode(['role' => $donnees['role']]),
            'date_heure' => now(),
        ]);

        return response()->json($this->presenter($utilisateur), 201);
    }

    private function presenter(Utilisateur $utilisateur): array
    {
        return [
            'id' => $utilisateur->id,
            'nom' => $utilisateur->nom,
            'prenom' => $utilisateur->prenom,
            'email' => $utilisateur->email,
            'role' => $utilisateur->role->value,
            'statut_mfa' => $utilisateur->statut_mfa,
            'actif' => $utilisateur->actif,
        ];
    }
}
