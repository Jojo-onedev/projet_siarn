<?php

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Models\Alerte;
use App\Models\Etudiant;
use App\Models\Note;
use App\Models\Reclamation;
use Illuminate\Http\Request;

// §7.2, §7.6, §7.7, UC-06, E12 : portail etudiant. Chaque methode resout
// l'etudiant depuis etudiants.utilisateur_id (jamais un id fourni par le
// client) : un etudiant ne peut structurellement pas consulter les donnees
// d'un autre (§5 : "Consulter ses notes" -> Etudiant uniquement, implicitement
// restreint aux SIENNES).
class EtudiantPortailController extends Controller
{
    private function etudiantConnecte(Request $request): ?Etudiant
    {
        return Etudiant::where('utilisateur_id', $request->user()->id)->first();
    }

    public function profil(Request $request)
    {
        $etudiant = $this->etudiantConnecte($request);
        if (! $etudiant) {
            return response()->json(['message' => 'Aucun profil etudiant associe a ce compte.'], 422);
        }

        return response()->json($etudiant->load('filiere')->only(['id', 'matricule', 'nom', 'prenom', 'niveau', 'annee_academique'])
            + ['filiere' => $etudiant->filiere ? ['id' => $etudiant->filiere->id, 'nom' => $etudiant->filiere->nom] : null]);
    }

    /**
     * §9.1 : seules les notes des PV 'publie' sont visibles - jamais une
     * note encore en cours de verification/validation (0 note publiee sans
     * validation humaine explicite, regle non negociable du projet).
     */
    public function notes(Request $request)
    {
        $etudiant = $this->etudiantConnecte($request);
        if (! $etudiant) {
            return response()->json(['message' => 'Aucun profil etudiant associe a ce compte.'], 422);
        }

        $requete = Note::where('etudiant_id', $etudiant->id)
            ->whereHas('pv', fn ($q) => $q->where('statut', 'publie'))
            ->with('pv');

        if ($request->filled('semestre')) {
            $requete->whereHas('pv', fn ($q) => $q->where('semestre', $request->query('semestre')));
        }
        if ($request->filled('annee_academique')) {
            $requete->whereHas('pv', fn ($q) => $q->where('annee_academique', $request->query('annee_academique')));
        }

        return response()->json($requete->get()->map(fn (Note $n) => [
            'code_matiere' => $n->pv->code_matiere,
            'semestre' => $n->pv->semestre,
            'annee_academique' => $n->pv->annee_academique,
            'valeur' => $n->valeur,
            'coefficient' => $n->coefficient,
            'credit' => $n->credit,
            'motif_penalite' => $n->motif_penalite,
        ]));
    }

    public function alertes(Request $request)
    {
        $alertes = Alerte::where('destinataire_id', $request->user()->id)
            ->orderByDesc('date_creation')
            ->get(['id', 'niveau', 'message', 'statut_lecture', 'date_creation']);

        return response()->json($alertes);
    }

    public function reclamations(Request $request)
    {
        $etudiant = $this->etudiantConnecte($request);
        if (! $etudiant) {
            return response()->json(['message' => 'Aucun profil etudiant associe a ce compte.'], 422);
        }

        $reclamations = Reclamation::where('etudiant_id', $etudiant->id)
            ->orderByDesc('date_creation')
            ->get(['id', 'motif', 'statut', 'reponse', 'date_creation', 'date_traitement']);

        return response()->json($reclamations);
    }
}
