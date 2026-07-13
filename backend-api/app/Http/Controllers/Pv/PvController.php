<?php

namespace App\Http\Controllers\Pv;

use App\Enums\StatutPv;
use App\Http\Controllers\Controller;
use App\Models\ProcesVerbal;
use App\Services\Audit\JournalAuditService;
use App\Services\Ocr\PretraitementClientService;
use App\StateMachines\MachineEtatsPv;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// §7.3 : import de lots de PV scannes (reserve Agent scolarite, §5 RBAC),
// pretraitement OpenCV + segmentation (E3). L'extraction OCR des champs
// (matricules/notes) releve de E6/E7 - ce controleur ne fait jamais avancer
// un PV au-dela de 'en_traitement' (cf. MachineEtatsPv, §9.1).
class PvController extends Controller
{
    public function __construct(
        private readonly MachineEtatsPv $machineEtats,
        private readonly PretraitementClientService $ocrClient,
        private readonly JournalAuditService $journalAudit,
    ) {}

    public function index(Request $request)
    {
        $requete = ProcesVerbal::with(['filiere', 'deposePar'])->orderByDesc('created_at');

        if ($request->filled('statut')) {
            $requete->where('statut', $request->query('statut'));
        }
        if ($request->filled('filiere_id')) {
            $requete->where('filiere_id', $request->query('filiere_id'));
        }

        $page = $requete->paginate((int) $request->query('par_page', 25));

        return response()->json([
            'donnees' => collect($page->items())->map(fn (ProcesVerbal $pv) => $this->presenter($pv)),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'dernieres_pages' => $page->lastPage(),
        ]);
    }

    public function show(ProcesVerbal $pv)
    {
        $pv->load(['filiere', 'deposePar', 'transitions']);

        return response()->json($this->presenter($pv) + [
            'historique' => $pv->transitions->map(fn ($t) => [
                'ancien_statut' => $t->ancien_statut?->value,
                'nouveau_statut' => $t->nouveau_statut->value,
                'motif' => $t->motif,
                'date_heure' => $t->date_heure,
            ]),
        ]);
    }

    public function importer(Request $request)
    {
        $config = config('siarn.import_pv');

        $donnees = $request->validate([
            'fichiers' => ['required', 'array', 'min:1'],
            'fichiers.*' => ['file', 'mimes:'.implode(',', $config['extensions_autorisees']), 'max:'.$config['taille_max_ko']],
            'code_matiere' => ['required', 'string', 'max:30'],
            'filiere_id' => ['required', 'uuid', 'exists:filieres,id'],
            'module_id' => ['nullable', 'uuid', 'exists:modules,id'],
            'semestre' => ['required', 'string', 'max:10'],
            'annee_academique' => ['required', 'string', 'max:9'],
            'type_gabarit' => ['nullable', 'string', 'max:100'],
        ]);

        $typeGabarit = $donnees['type_gabarit'] ?? 'defaut';
        $acteur = $request->user();
        $resultats = [];

        foreach ($request->file('fichiers') as $fichier) {
            $pv = $this->importerUnFichier($fichier, $donnees, $typeGabarit, $acteur);
            $resultats[] = $this->presenter($pv);
        }

        return response()->json(['pv_importes' => $resultats], 201);
    }

    private function importerUnFichier(UploadedFile $fichier, array $donnees, string $typeGabarit, $acteur): ProcesVerbal
    {
        $nomStocke = Str::uuid().'.'.$fichier->getClientOriginalExtension();
        $chemin = Storage::disk('pv')->putFileAs('originaux', $fichier, $nomStocke);
        $hash = hash_file('sha256', $fichier->getRealPath());

        $pv = ProcesVerbal::create([
            'nom_fichier' => $fichier->getClientOriginalName(),
            'chemin_fichier' => $chemin,
            'hash_fichier' => $hash,
            'code_matiere' => $donnees['code_matiere'],
            'module_id' => $donnees['module_id'] ?? null,
            'filiere_id' => $donnees['filiere_id'],
            'semestre' => $donnees['semestre'],
            'annee_academique' => $donnees['annee_academique'],
            'date_scan' => now(),
            'statut' => StatutPv::Soumis,
            'depose_par_id' => $acteur->id,
            'type_gabarit' => $typeGabarit,
        ]);

        $this->machineEtats->enregistrerCreation($pv, $acteur);
        $pv = $this->machineEtats->transitionner($pv, StatutPv::EnTraitement, $acteur);

        try {
            $resultat = $this->ocrClient->pretraiter($fichier, $typeGabarit);

            $cheminPretraite = null;
            if (! empty($resultat['image_pretraitee_base64'])) {
                $cheminPretraite = "pretraitees/{$pv->id}.png";
                Storage::disk('pv')->put($cheminPretraite, base64_decode($resultat['image_pretraitee_base64']));
            }

            $pv->update([
                'chemin_image_pretraitee' => $cheminPretraite,
                'zones_segmentees' => $resultat['zones'] ?? null,
            ]);

            $this->journalAudit->enregistrer('pv.pretraitement_reussi', $acteur->id, 'proces_verbal', $pv->id, [
                'zones_detectees' => count($resultat['zones'] ?? []),
            ]);
        } catch (\Throwable $e) {
            $pv = $this->machineEtats->transitionner(
                $pv,
                StatutPv::ErreurExtraction,
                $acteur,
                'Pretraitement echoue : '.$e->getMessage(),
            );
        }

        return $pv->fresh();
    }

    private function presenter(ProcesVerbal $pv): array
    {
        return [
            'id' => $pv->id,
            'nom_fichier' => $pv->nom_fichier,
            'code_matiere' => $pv->code_matiere,
            'filiere' => $pv->filiere ? ['id' => $pv->filiere->id, 'nom' => $pv->filiere->nom] : null,
            'semestre' => $pv->semestre,
            'annee_academique' => $pv->annee_academique,
            'statut' => $pv->statut->value,
            'type_gabarit' => $pv->type_gabarit,
            'zones_segmentees' => $pv->zones_segmentees,
            'depose_par' => $pv->deposePar ? ['id' => $pv->deposePar->id, 'nom' => $pv->deposePar->nom, 'prenom' => $pv->deposePar->prenom] : null,
            'created_at' => $pv->created_at,
        ];
    }
}
