<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Services\Audit\JournalAuditService;
use App\Services\Notes\CalculMoyenneService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// §7.2 : referentiel etudiants, recherche multicritere, import de listes.
class EtudiantController extends Controller
{
    public function __construct(
        private readonly JournalAuditService $journalAudit,
        private readonly CalculMoyenneService $calculMoyenne,
    ) {}

    public function index(Request $request)
    {
        $requete = Etudiant::with('filiere')->orderBy('nom');

        if ($request->filled('q')) {
            $recherche = '%'.$request->query('q').'%';
            $requete->where(fn ($q) => $q->where('nom', 'ilike', $recherche)
                ->orWhere('prenom', 'ilike', $recherche)
                ->orWhere('matricule', 'ilike', $recherche));
        }
        if ($request->filled('filiere_id')) {
            $requete->where('filiere_id', $request->query('filiere_id'));
        }
        if ($request->filled('niveau')) {
            $requete->where('niveau', $request->query('niveau'));
        }
        if ($request->filled('annee_academique')) {
            $requete->where('annee_academique', $request->query('annee_academique'));
        }

        $page = $requete->paginate((int) $request->query('par_page', 25));

        return response()->json([
            'donnees' => collect($page->items())->map(fn (Etudiant $e) => $this->presenter($e)),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'dernieres_pages' => $page->lastPage(),
        ]);
    }

    public function show(Etudiant $etudiant)
    {
        return response()->json($this->presenter($etudiant->load('filiere')));
    }

    /**
     * §7.6 : calcul automatique de la moyenne ponderee (notes 'valide'
     * uniquement, cf. CalculMoyenneService).
     */
    public function moyenne(Request $request, Etudiant $etudiant)
    {
        $donnees = $request->validate([
            'semestre' => ['required', 'string', 'max:10'],
            'annee_academique' => ['required', 'string', 'max:9'],
        ]);

        return response()->json($this->calculMoyenne->calculer($etudiant, $donnees['semestre'], $donnees['annee_academique']));
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);

        $etudiant = Etudiant::create($donnees + ['actif' => true]);

        $this->journalAudit->enregistrer('etudiant.creation', $request->user()->id, 'etudiant', $etudiant->id, ['matricule' => $etudiant->matricule]);

        return response()->json($this->presenter($etudiant->load('filiere')), 201);
    }

    public function update(Request $request, Etudiant $etudiant)
    {
        $donnees = $this->valider($request, $etudiant->id);

        $etudiant->update($donnees);

        $this->journalAudit->enregistrer('etudiant.modification', $request->user()->id, 'etudiant', $etudiant->id, $donnees);

        return response()->json($this->presenter($etudiant->load('filiere')));
    }

    /**
     * Import de liste (§7.2). CSV avec en-tete :
     * matricule,nom,prenom,filiere_code,niveau,annee_academique
     * Upsert par matricule. Rapporte les erreurs ligne par ligne plutot que
     * d'echouer l'import entier sur une seule ligne invalide.
     */
    public function importer(Request $request)
    {
        $request->validate(['fichier' => ['required', 'file', 'mimes:csv,txt']]);

        $poignee = fopen($request->file('fichier')->getRealPath(), 'r');
        $entetes = fgetcsv($poignee);
        // Les fichiers exportes depuis Excel (format courant pour les agents
        // de scolarite, cf. §14 ergonomie) demarrent quasi systematiquement
        // par un BOM UTF-8, qui casse sinon la cle du premier en-tete
        // ("matricule" devient "\xEF\xBB\xBFmatricule" et array_combine ne
        // matche plus jamais cette colonne).
        if (isset($entetes[0])) {
            $entetes[0] = ltrim($entetes[0], "\xEF\xBB\xBF");
        }

        $crees = 0;
        $misAJour = 0;
        $erreurs = [];
        $numeroLigne = 1;

        while (($ligne = fgetcsv($poignee)) !== false) {
            $numeroLigne++;
            $rangee = array_combine($entetes, $ligne);

            $filiere = Filiere::where('code', $rangee['filiere_code'] ?? null)->first();

            if (! $filiere) {
                $erreurs[] = ['ligne' => $numeroLigne, 'message' => 'Filiere introuvable : '.($rangee['filiere_code'] ?? '')];

                continue;
            }
            if (empty($rangee['matricule']) || empty($rangee['nom']) || empty($rangee['prenom'])) {
                $erreurs[] = ['ligne' => $numeroLigne, 'message' => 'Champs obligatoires manquants (matricule/nom/prenom)'];

                continue;
            }

            $existant = Etudiant::where('matricule', $rangee['matricule'])->first();
            $valeurs = [
                'nom' => $rangee['nom'],
                'prenom' => $rangee['prenom'],
                'filiere_id' => $filiere->id,
                'niveau' => $rangee['niveau'] ?? '',
                'annee_academique' => $rangee['annee_academique'] ?? '',
            ];

            if ($existant) {
                $existant->update($valeurs);
                $misAJour++;
            } else {
                Etudiant::create($valeurs + ['matricule' => $rangee['matricule'], 'actif' => true]);
                $crees++;
            }
        }

        fclose($poignee);

        $this->journalAudit->enregistrer(
            'etudiant.import',
            $request->user()->id,
            'etudiant',
            null,
            ['crees' => $crees, 'mis_a_jour' => $misAJour, 'erreurs' => count($erreurs)],
        );

        return response()->json(['crees' => $crees, 'mis_a_jour' => $misAJour, 'erreurs' => $erreurs]);
    }

    private function valider(Request $request, ?string $etudiantId = null): array
    {
        return $request->validate([
            'matricule' => ['required', 'string', 'max:30', Rule::unique('etudiants', 'matricule')->ignore($etudiantId)],
            'nom' => ['required', 'string', 'max:150'],
            'prenom' => ['required', 'string', 'max:150'],
            'filiere_id' => ['required', 'uuid', 'exists:filieres,id'],
            'niveau' => ['required', 'string', 'max:20'],
            'annee_academique' => ['required', 'string', 'max:9'],
        ]);
    }

    private function presenter(Etudiant $etudiant): array
    {
        return [
            'id' => $etudiant->id,
            'matricule' => $etudiant->matricule,
            'nom' => $etudiant->nom,
            'prenom' => $etudiant->prenom,
            'filiere' => $etudiant->filiere ? ['id' => $etudiant->filiere->id, 'nom' => $etudiant->filiere->nom] : null,
            'niveau' => $etudiant->niveau,
            'annee_academique' => $etudiant->annee_academique,
            'actif' => $etudiant->actif,
        ];
    }
}
