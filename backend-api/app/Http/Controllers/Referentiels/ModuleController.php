<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\Audit\JournalAuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// §7.2 : modules/matieres rattaches a une filiere (necessaires pour
// PV.code_matiere et Absence.module_id, cf. commentaire de conception §10).
class ModuleController extends Controller
{
    public function __construct(private readonly JournalAuditService $journalAudit) {}

    public function index(Request $request)
    {
        $requete = Module::with('filiere')->orderBy('nom');

        if ($request->filled('filiere_id')) {
            $requete->where('filiere_id', $request->query('filiere_id'));
        }

        return response()->json($requete->get()->map(fn (Module $m) => $this->presenter($m)));
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);

        $module = Module::create($donnees + ['actif' => true]);

        $this->journalAudit->enregistrer('module.creation', $request->user()->id, 'module', $module->id, $donnees);

        return response()->json($this->presenter($module->load('filiere')), 201);
    }

    public function update(Request $request, Module $module)
    {
        $donnees = $this->valider($request, $module->id);

        $module->update($donnees);

        $this->journalAudit->enregistrer('module.modification', $request->user()->id, 'module', $module->id, $donnees);

        return response()->json($this->presenter($module->load('filiere')));
    }

    private function valider(Request $request, ?string $moduleId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('modules', 'code')->ignore($moduleId)],
            'nom' => ['required', 'string', 'max:200'],
            'filiere_id' => ['required', 'uuid', 'exists:filieres,id'],
            'niveau' => ['required', 'string', 'max:20'],
            'semestre' => ['required', 'string', 'max:10'],
            'coefficient' => ['required', 'numeric', 'min:0'],
            'credits' => ['required', 'numeric', 'min:0'],
            'enseignant_id' => [
                'nullable', 'uuid',
                Rule::exists('utilisateurs', 'id')->where(fn ($q) => $q->where('role', 'enseignant')),
            ],
        ]);
    }

    private function presenter(Module $module): array
    {
        return [
            'id' => $module->id,
            'code' => $module->code,
            'nom' => $module->nom,
            'filiere' => $module->filiere ? ['id' => $module->filiere->id, 'nom' => $module->filiere->nom] : null,
            'niveau' => $module->niveau,
            'semestre' => $module->semestre,
            'coefficient' => $module->coefficient,
            'credits' => $module->credits,
            'actif' => $module->actif,
            'enseignant_id' => $module->enseignant_id,
        ];
    }
}
