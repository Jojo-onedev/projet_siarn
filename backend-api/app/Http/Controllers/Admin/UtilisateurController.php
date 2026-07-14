<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use App\Services\Audit\JournalAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

// Gestion des comptes/roles (§5 RBAC : reserve a Admin ; §7.1). Le
// provisionnement (creation de compte) reste manuel/admin en V1 : pas de flux
// d'inscription self-service (hors perimetre RBAC pour les roles internes).
class UtilisateurController extends Controller
{
    public function __construct(private readonly JournalAuditService $journalAudit) {}

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

        $this->journalAudit->enregistrer(
            'utilisateur.creation',
            $request->user()->id,
            'utilisateur',
            $utilisateur->id,
            ['role' => $donnees['role']],
        );

        return response()->json($this->presenter($utilisateur), 201);
    }

    /**
     * §13.6/§7.1 : un compte ayant un historique d'audit ne peut jamais etre
     * supprime (contrainte ON DELETE RESTRICT sur journal_audit.acteur_id),
     * uniquement desactive. Jusqu'ici, aucune route ne permettait de
     * modifier `actif` apres la creation - trouve en revue manuelle (aucun
     * moyen de bloquer un compte compromis ou un agent parti). Desactiver
     * prend effet immediatement : JwtGuard::user() rejette deja tout token
     * dont l'utilisateur associe a `actif=false` (aucune revocation de
     * session supplementaire necessaire ici).
     */
    public function update(Request $request, Utilisateur $utilisateur)
    {
        $donnees = $request->validate([
            'actif' => ['required', 'boolean'],
        ]);

        if ($utilisateur->id === $request->user()->id && ! $donnees['actif']) {
            return response()->json(['message' => 'Vous ne pouvez pas desactiver votre propre compte.'], 422);
        }

        $utilisateur->update($donnees);

        $this->journalAudit->enregistrer(
            $donnees['actif'] ? 'utilisateur.reactivation' : 'utilisateur.desactivation',
            $request->user()->id,
            'utilisateur',
            $utilisateur->id,
            [],
        );

        return response()->json($this->presenter($utilisateur->fresh()));
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
