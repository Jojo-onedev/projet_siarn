<?php

namespace App\Http\Controllers\Reclamations;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Reclamation;
use App\Services\Audit\JournalAuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// §7.7, UC-07 : gestion des reclamations etudiants. §5 RBAC : "Initier
// reclamation" -> Etudiant uniquement (et seulement la sienne, via le lien
// etudiants.utilisateur_id) ; le traitement est ouvert au personnel de
// scolarite/hierarchie (aucune ligne dediee dans la matrice §5, choix
// conservateur aligne sur les autres actions operationnelles/hierarchiques).
class ReclamationController extends Controller
{
    public function __construct(private readonly JournalAuditService $journalAudit) {}

    public function index(Request $request)
    {
        $requete = Reclamation::with(['etudiant', 'note'])->orderByDesc('date_creation');

        if ($request->filled('statut')) {
            $requete->where('statut', $request->query('statut'));
        }

        return response()->json($requete->get()->map(fn (Reclamation $r) => $this->presenter($r)));
    }

    /**
     * §5 : l'etudiant ne consulte/cree que SES PROPRES reclamations, jamais
     * celles d'un autre - resolu via son compte utilisateur lie (etudiants.utilisateur_id).
     */
    public function store(Request $request)
    {
        $etudiant = Etudiant::where('utilisateur_id', $request->user()->id)->first();
        if (! $etudiant) {
            return response()->json(['message' => 'Aucun profil etudiant associe a ce compte.'], 422);
        }

        $donnees = $request->validate([
            'note_id' => ['nullable', 'uuid', 'exists:notes,id'],
            'motif' => ['required', 'string', 'max:2000'],
        ]);

        $reclamation = Reclamation::create($donnees + [
            'etudiant_id' => $etudiant->id,
            'statut' => 'ouverte',
            'date_creation' => now(),
        ]);

        $this->journalAudit->enregistrer('reclamation.creation', $request->user()->id, 'reclamation', $reclamation->id, $donnees);

        return response()->json($this->presenter($reclamation->fresh(['etudiant', 'note'])), 201);
    }

    public function repondre(Request $request, Reclamation $reclamation)
    {
        $donnees = $request->validate([
            'statut' => ['required', Rule::in(['en_traitement', 'resolue', 'rejetee'])],
            'reponse' => ['required', 'string', 'max:2000'],
        ]);

        $reclamation->update($donnees + [
            'traite_par_id' => $request->user()->id,
            'date_traitement' => now(),
        ]);

        $this->journalAudit->enregistrer('reclamation.traitement', $request->user()->id, 'reclamation', $reclamation->id, $donnees);

        return response()->json($this->presenter($reclamation->fresh(['etudiant', 'note'])));
    }

    private function presenter(Reclamation $reclamation): array
    {
        return [
            'id' => $reclamation->id,
            'etudiant' => $reclamation->etudiant ? [
                'id' => $reclamation->etudiant->id, 'matricule' => $reclamation->etudiant->matricule,
                'nom' => $reclamation->etudiant->nom, 'prenom' => $reclamation->etudiant->prenom,
            ] : null,
            'note_id' => $reclamation->note_id,
            'motif' => $reclamation->motif,
            'statut' => $reclamation->statut,
            'reponse' => $reclamation->reponse,
            'date_creation' => $reclamation->date_creation,
            'date_traitement' => $reclamation->date_traitement,
        ];
    }
}
