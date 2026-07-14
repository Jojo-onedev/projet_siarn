<?php

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Note;
use Illuminate\Http\Request;

/**
 * §5 RBAC : "Verifier son propre PV numerise (lecture + signalement fraude)"
 * -> Enseignant uniquement, scope par module dont il est le referent
 * (modules.enseignant_id). Ecran manquant jusqu'ici : le backend n'exposait
 * que POST /notes/{id}/signaler-fraude (sur un note_id deja connu), sans
 * aucune route pour qu'un enseignant retrouve ses propres modules/notes -
 * ce role n'avait donc, en pratique, aucun ecran fonctionnel.
 *
 * Volontairement pas de filtre sur proces_verbaux.statut : un signalement
 * de fraude a plus de valeur avant publication qu'apres (§7.7/§9.1),
 * l'enseignant doit pouvoir verifier des l'extraction/verification, pas
 * seulement une fois le dossier deja publie.
 */
class EnseignantPortailController extends Controller
{
    public function mesModules(Request $request)
    {
        $modules = Module::with('filiere')
            ->where('enseignant_id', $request->user()->id)
            ->orderBy('nom')
            ->get();

        return response()->json($modules->map(fn (Module $m) => [
            'id' => $m->id,
            'code' => $m->code,
            'nom' => $m->nom,
            'filiere' => $m->filiere ? ['id' => $m->filiere->id, 'nom' => $m->filiere->nom] : null,
            'niveau' => $m->niveau,
            'semestre' => $m->semestre,
        ]));
    }

    public function notesDuModule(Request $request, Module $module)
    {
        if ($module->enseignant_id !== $request->user()->id) {
            return response()->json(['message' => "Vous n'etes pas l'enseignant referent de ce module."], 403);
        }

        $notes = Note::with(['etudiant', 'pv'])
            ->whereHas('pv', fn ($q) => $q->where('module_id', $module->id))
            ->get();

        return response()->json($notes->map(fn (Note $n) => [
            'id' => $n->id,
            'etudiant' => $n->etudiant ? [
                'id' => $n->etudiant->id, 'matricule' => $n->etudiant->matricule,
                'nom' => $n->etudiant->nom, 'prenom' => $n->etudiant->prenom,
            ] : null,
            'pv' => $n->pv ? [
                'id' => $n->pv->id, 'statut' => $n->pv->statut->value,
                'semestre' => $n->pv->semestre, 'annee_academique' => $n->pv->annee_academique,
            ] : null,
            'valeur' => $n->valeur,
            'etat_validation' => $n->etat_validation,
            'motif_penalite' => $n->motif_penalite,
        ]));
    }
}
