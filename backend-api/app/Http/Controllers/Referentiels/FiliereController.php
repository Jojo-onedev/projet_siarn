<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Models\Filiere;
use App\Services\Audit\JournalAuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// §7.2 (referentiel filieres). chef_departement_id nullable (§4, §10) :
// aucune contrainte "obligatoire" ici, les deux configurations
// organisationnelles (3 chefs distincts / 1 responsable cumulant) doivent
// fonctionner sans changement de code.
class FiliereController extends Controller
{
    public function __construct(private readonly JournalAuditService $journalAudit) {}

    public function index(Request $request)
    {
        return response()->json(
            Filiere::with('chefDepartement')->orderBy('nom')->get()->map(fn (Filiere $f) => $this->presenter($f))
        );
    }

    public function show(Filiere $filiere)
    {
        return response()->json($this->presenter($filiere->load('chefDepartement')));
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);

        $filiere = Filiere::create($donnees + ['actif' => true]);

        $this->journalAudit->enregistrer('filiere.creation', $request->user()->id, 'filiere', $filiere->id, $donnees);

        return response()->json($this->presenter($filiere->load('chefDepartement')), 201);
    }

    public function update(Request $request, Filiere $filiere)
    {
        $donnees = $this->valider($request, $filiere->id);

        $filiere->update($donnees);

        $this->journalAudit->enregistrer('filiere.modification', $request->user()->id, 'filiere', $filiere->id, $donnees);

        return response()->json($this->presenter($filiere->load('chefDepartement')));
    }

    private function valider(Request $request, ?string $filiereId = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', Rule::unique('filieres', 'code')->ignore($filiereId)],
            // Reference obligatoirement un utilisateur chef_departement OU
            // responsable_academique (regle metier §4, verifiee ici car le
            // schema ne peut pas contraindre le role via une simple FK).
            'chef_departement_id' => [
                'nullable', 'uuid',
                Rule::exists('utilisateurs', 'id')->where(
                    fn ($query) => $query->whereIn('role', ['chef_departement', 'responsable_academique'])
                ),
            ],
            // §13.6 : desactivation (jamais de suppression) - "sometimes" pour
            // ne pas casser la creation, qui force toujours actif=true.
            'actif' => ['sometimes', 'boolean'],
        ]);
    }

    private function presenter(Filiere $filiere): array
    {
        return [
            'id' => $filiere->id,
            'nom' => $filiere->nom,
            'code' => $filiere->code,
            'chef_departement' => $filiere->chefDepartement ? [
                'id' => $filiere->chefDepartement->id,
                'nom' => $filiere->chefDepartement->nom,
                'prenom' => $filiere->chefDepartement->prenom,
                'role' => $filiere->chefDepartement->role->value,
            ] : null,
            'actif' => $filiere->actif,
        ];
    }
}
