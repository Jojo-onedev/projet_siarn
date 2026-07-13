<?php

namespace App\Http\Controllers\Absences;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Etudiant;
use App\Models\Module;
use App\Services\Audit\JournalAuditService;
use App\Services\Notes\ReglesPenaliteService;
use Illuminate\Http\Request;

// §7.6 : declaration des absences, base du calcul de cumul pour la penalite
// automatique (ReglesPenaliteService). Pas de role dedie dans la matrice
// §5 : ouvert a agent_scolarite/enseignant/admin (suivi de presence).
class AbsenceController extends Controller
{
    public function __construct(
        private readonly JournalAuditService $journalAudit,
        private readonly ReglesPenaliteService $reglesPenalite,
    ) {}

    public function index(Request $request)
    {
        $requete = Absence::with(['etudiant', 'module'])->orderByDesc('date');

        if ($request->filled('etudiant_id')) {
            $requete->where('etudiant_id', $request->query('etudiant_id'));
        }
        if ($request->filled('module_id')) {
            $requete->where('module_id', $request->query('module_id'));
        }

        return response()->json($requete->get()->map(fn (Absence $a) => $this->presenter($a)));
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'etudiant_id' => ['required', 'uuid', 'exists:etudiants,id'],
            'module_id' => ['required', 'uuid', 'exists:modules,id'],
            'duree_heures' => ['required', 'numeric', 'min:0.5'],
            'date' => ['required', 'date'],
            'justifiee' => ['nullable', 'boolean'],
        ]);

        $absence = Absence::create($donnees + [
            'justifiee' => $donnees['justifiee'] ?? false,
            'declare_par_id' => $request->user()->id,
            'created_at' => now(),
        ]);

        $this->journalAudit->enregistrer('absence.declaration', $request->user()->id, 'absence', $absence->id, $donnees);

        if (! $absence->justifiee) {
            $etudiant = Etudiant::find($donnees['etudiant_id']);
            $module = Module::find($donnees['module_id']);
            $this->reglesPenalite->verifierEtAppliquerPenaliteAbsence($etudiant, $module, $request->user());
        }

        return response()->json($this->presenter($absence->fresh(['etudiant', 'module'])), 201);
    }

    private function presenter(Absence $absence): array
    {
        return [
            'id' => $absence->id,
            'etudiant' => $absence->etudiant ? ['id' => $absence->etudiant->id, 'matricule' => $absence->etudiant->matricule] : null,
            'module' => $absence->module ? ['id' => $absence->module->id, 'code' => $absence->module->code] : null,
            'duree_heures' => $absence->duree_heures,
            'date' => $absence->date,
            'justifiee' => $absence->justifiee,
        ];
    }
}
