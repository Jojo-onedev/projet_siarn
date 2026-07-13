<?php

namespace App\Http\Controllers\Notes;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Note;
use App\Models\ProcesVerbal;
use App\Services\Audit\JournalAuditService;
use App\Services\Notes\ReglesPenaliteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// §7.6/§9 : les notes structurees (par etudiant) sont saisies par l'agent
// pendant la verification humaine (E7), en s'appuyant sur le texte OCR de
// la zone 'tableau_notes' (PvController::champs_extraits) comme reference -
// aucune extraction automatique de table n'est faite (segmentation §7.3
// actuelle : une zone = un bloc de texte, pas une reconnaissance de tableau).
class NoteController extends Controller
{
    public function __construct(
        private readonly JournalAuditService $journalAudit,
        private readonly ReglesPenaliteService $reglesPenalite,
    ) {}

    public function index(ProcesVerbal $pv)
    {
        return response()->json(
            $pv->notes()->with('etudiant')->get()->map(fn (Note $n) => $this->presenter($n))
        );
    }

    public function store(Request $request, ProcesVerbal $pv)
    {
        if (! in_array($pv->statut->value, ['en_verification', 'en_validation'], true)) {
            return response()->json(['message' => "Ce PV (statut {$pv->statut->value}) n'accepte pas de saisie de notes."], 409);
        }

        $donnees = $request->validate([
            'etudiant_id' => ['required', 'uuid', 'exists:etudiants,id'],
            'valeur' => ['required', 'numeric', 'min:0', 'max:20'],
            'coefficient' => ['nullable', 'numeric', 'min:0'],
            'credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $module = $pv->module;

        $note = Note::updateOrCreate(
            ['pv_id' => $pv->id, 'etudiant_id' => $donnees['etudiant_id']],
            [
                'valeur' => $donnees['valeur'],
                'coefficient' => $donnees['coefficient'] ?? $module?->coefficient ?? 1,
                'credit' => $donnees['credit'] ?? $module?->credits ?? 0,
                'etat_validation' => 'corrige',
                'cree_par_id' => $request->user()->id,
            ]
        );

        $this->journalAudit->enregistrer('note.saisie', $request->user()->id, 'note', $note->id, [
            'pv_id' => $pv->id, 'etudiant_id' => $donnees['etudiant_id'], 'valeur' => $donnees['valeur'],
        ]);

        if ($module) {
            $etudiant = Etudiant::find($donnees['etudiant_id']);
            $this->reglesPenalite->verifierEtAppliquerPenaliteAbsence($etudiant, $module, $request->user());
        }

        return response()->json($this->presenter($note->fresh('etudiant')), 201);
    }

    /**
     * §5 RBAC : "Verifier son propre PV numerise (lecture + signalement
     * fraude)" -> Enseignant uniquement, et seulement pour un module dont
     * il est l'enseignant reference (modules.enseignant_id).
     */
    public function signalerFraude(Request $request, Note $note)
    {
        $module = $note->pv?->module;
        if (! $module || $module->enseignant_id !== $request->user()->id) {
            return response()->json(['message' => "Vous n'etes pas l'enseignant reference de ce module."], 403);
        }

        $donnees = $request->validate(['motif' => ['required', 'string', 'max:1000']]);

        $note = $this->reglesPenalite->appliquerPenaliteFraude($note, $donnees['motif'], $request->user());

        return response()->json($this->presenter($note));
    }

    private function presenter(Note $note): array
    {
        return [
            'id' => $note->id,
            'pv_id' => $note->pv_id,
            'etudiant' => $note->etudiant ? [
                'id' => $note->etudiant->id, 'matricule' => $note->etudiant->matricule,
                'nom' => $note->etudiant->nom, 'prenom' => $note->etudiant->prenom,
            ] : null,
            'valeur' => $note->valeur,
            'coefficient' => $note->coefficient,
            'credit' => $note->credit,
            'etat_validation' => $note->etat_validation,
            'motif_penalite' => $note->motif_penalite,
            'motif_penalite_detail' => $note->motif_penalite_detail,
        ];
    }
}
