<?php

namespace App\Http\Controllers\Pv;

use App\Enums\RoleUtilisateur;
use App\Enums\StatutPv;
use App\Http\Controllers\Controller;
use App\Models\Decision;
use App\Models\ModeleOcr;
use App\Models\ProcesVerbal;
use App\Services\Audit\JournalAuditService;
use App\Services\Corpus\RetroactionCorpusService;
use App\Services\Notifications\NotificationPublicationService;
use App\Services\Ocr\OcrClientService;
use App\StateMachines\MachineEtatsPv;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// §7.3/§8.3 : import de lots de PV, pretraitement OpenCV + segmentation (E3),
// extraction OCR avec score de confiance par champ (E6), verification/
// correction humaine (E7, §7.5) avec boucle de retroaction vers le corpus
// (§8.4). Machine a etats explicite (MachineEtatsPv, §9.1) - jamais de
// transition ad hoc dans ce controleur.
class PvController extends Controller
{
    public function __construct(
        private readonly MachineEtatsPv $machineEtats,
        private readonly OcrClientService $ocrClient,
        private readonly JournalAuditService $journalAudit,
        private readonly RetroactionCorpusService $retroaction,
        private readonly NotificationPublicationService $notificationPublication,
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

    /**
     * Verification humaine (§7.5, §9.1, E7) : reserve a l'agent de scolarite
     * (§5 RBAC : "Corriger donnees OCR" -> Agent scolarite uniquement).
     * Accepte des corrections partielles (plusieurs appels possibles) ; la
     * transition en_verification -> en_validation n'a lieu que lorsque TOUS
     * les champs ont ete valides.
     */
    public function verifier(Request $request, ProcesVerbal $pv)
    {
        if ($pv->statut !== StatutPv::EnVerification) {
            return response()->json(['message' => "Ce PV n'est pas en attente de verification (statut actuel : {$pv->statut->value})."], 409);
        }

        $donnees = $request->validate([
            'corrections' => ['required', 'array', 'min:1'],
            'corrections.*.champ' => ['required', 'string'],
            'corrections.*.valeur_validee' => ['required', 'string'],
        ]);

        $acteur = $request->user();
        $champs = collect($pv->champs_extraits ?? []);

        foreach ($donnees['corrections'] as $correction) {
            $index = $champs->search(fn ($c) => $c['champ'] === $correction['champ']);
            if ($index === false) {
                continue;
            }

            $champ = $champs[$index];
            $champ['valeur_validee'] = $correction['valeur_validee'];
            $champ['corrige_par_id'] = $acteur->id;
            $champ['date_verification'] = now()->toIso8601String();
            $champs[$index] = $champ;

            $this->journalAudit->enregistrer('pv.champ_verifie', $acteur->id, 'proces_verbal', $pv->id, [
                'champ' => $correction['champ'],
                'valeur_ocr' => $champ['valeur_ocr'] ?? null,
                'valeur_validee' => $correction['valeur_validee'],
            ]);

            if (($champ['valeur_ocr'] ?? null) !== $correction['valeur_validee']) {
                $this->retroaction->exporterCorrection($pv, $correction['champ'], $correction['valeur_validee'], $acteur);
            }
        }

        $pv->update(['champs_extraits' => $champs->values()->all()]);

        $tousValides = $champs->every(fn ($c) => ! empty($c['valeur_validee']));
        if ($tousValides) {
            $pv = $this->machineEtats->transitionner($pv, StatutPv::EnValidation, $acteur);
        }

        return response()->json($this->presenter($pv->fresh()));
    }

    /**
     * Validation hierarchique (§7.6, §9.1, E8). RBAC §5 : "Valider dossier
     * de sa filiere" -> Chef de departement (sa filiere uniquement) ou
     * Responsable academique (les 3 filieres) - le role seul (verifie par
     * le middleware 'role:...') ne suffit pas pour un chef de departement,
     * il faut aussi qu'il soit bien le chef de la filiere de ce PV precis.
     */
    public function valider(Request $request, ProcesVerbal $pv)
    {
        if ($pv->statut !== StatutPv::EnValidation) {
            return response()->json(['message' => "Ce PV n'est pas en attente de validation (statut actuel : {$pv->statut->value})."], 409);
        }

        $acteur = $request->user();
        if ($acteur->role === RoleUtilisateur::ChefDepartement && $pv->filiere->chef_departement_id !== $acteur->id) {
            return response()->json(['message' => "Vous n'etes pas le chef de departement de cette filiere."], 403);
        }

        $donnees = $request->validate([
            'decision' => ['required', Rule::in(['valider', 'rejeter', 'complement_requis'])],
            'motif' => ['required_unless:decision,valider', 'nullable', 'string', 'max:2000'],
        ]);

        Decision::create([
            'pv_id' => $pv->id,
            'type_decision' => $donnees['decision'],
            'motif' => $donnees['motif'] ?? null,
            'auteur_id' => $acteur->id,
            'date_decision' => now(),
        ]);

        $statutCible = match ($donnees['decision']) {
            'valider' => StatutPv::Valide,
            'rejeter' => StatutPv::Rejete,
            'complement_requis' => StatutPv::ComplementRequis,
        };

        $pv = $this->machineEtats->transitionner($pv, $statutCible, $acteur, $donnees['motif'] ?? null);

        if ($statutCible === StatutPv::Valide) {
            $pv->notes()->update(['etat_validation' => 'valide', 'valide_par_id' => $acteur->id]);
            $pv = $this->machineEtats->transitionner($pv, StatutPv::Integre, $acteur);
        }

        return response()->json($this->presenter($pv->fresh()));
    }

    /**
     * §7.7, §9.1 : publication des resultats - action deliberee (pas de
     * cascade automatique depuis 'integre', contrairement a valide->integre
     * qui n'a pas d'etape metier intermediaire decrite) : rendre les notes
     * visibles aux etudiants et declencher les notifications est une action
     * a forte visibilite, jamais silencieuse.
     */
    public function publier(Request $request, ProcesVerbal $pv)
    {
        if ($pv->statut !== StatutPv::Integre) {
            return response()->json(['message' => "Ce PV n'est pas pret pour la publication (statut actuel : {$pv->statut->value})."], 409);
        }

        $acteur = $request->user();
        $pv = $this->machineEtats->transitionner($pv, StatutPv::Publie, $acteur);

        $this->notificationPublication->notifierPublication($pv);

        return response()->json($this->presenter($pv->fresh()));
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
            return $this->machineEtats->transitionner(
                $pv,
                StatutPv::ErreurExtraction,
                $acteur,
                'Pretraitement echoue : '.$e->getMessage(),
            );
        }

        return $this->extraireChamps($pv->fresh(), $fichier, $typeGabarit, $acteur);
    }

    /**
     * §8.3/§9.1, E6 : applique le modele OCR actif. Sans modele actif, le PV
     * reste en 'en_traitement' (rien a extraire) - pas de repli silencieux
     * sur un OCR "pret a l'emploi" nom-versionne (regle non negociable §8).
     */
    private function extraireChamps(ProcesVerbal $pv, UploadedFile $fichier, string $typeGabarit, $acteur): ProcesVerbal
    {
        $modeleActif = ModeleOcr::where('statut', 'actif')->first();
        if (! $modeleActif) {
            return $pv;
        }

        try {
            $resultat = $this->ocrClient->extraire($fichier, $typeGabarit, $modeleActif->version);
        } catch (\Throwable $e) {
            return $this->machineEtats->transitionner(
                $pv, StatutPv::ErreurExtraction, $acteur, 'Extraction OCR echouee : '.$e->getMessage(),
            );
        }

        $champs = collect($resultat['champs'] ?? [])->map(fn ($c) => [
            'champ' => $c['champ'],
            'valeur_ocr' => $c['valeur'],
            'score_confiance' => $c['score_confiance'],
            'verification_requise' => $c['verification_requise'],
            'valeur_validee' => null,
            'corrige_par_id' => null,
            'date_verification' => null,
        ]);

        $confianceMoyenne = $champs->avg('score_confiance') ?? 0.0;
        $pv->update(['champs_extraits' => $champs->values()->all(), 'modele_ocr_id' => $modeleActif->id]);

        if ($confianceMoyenne < config('siarn.extraction.seuil_confiance_minimum')) {
            return $this->machineEtats->transitionner(
                $pv, StatutPv::ErreurExtraction, $acteur,
                sprintf('Confiance moyenne trop faible (%.2f%%)', $confianceMoyenne * 100),
            );
        }

        return $this->machineEtats->transitionner($pv, StatutPv::EnVerification, $acteur);
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
            'champs_extraits' => $pv->champs_extraits,
            'modele_ocr_id' => $pv->modele_ocr_id,
            'depose_par' => $pv->deposePar ? ['id' => $pv->deposePar->id, 'nom' => $pv->deposePar->nom, 'prenom' => $pv->deposePar->prenom] : null,
            'created_at' => $pv->created_at,
        ];
    }
}
