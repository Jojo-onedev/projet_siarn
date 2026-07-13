<?php

namespace App\Http\Controllers\Corpus;

use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\DocumentCorpus;
use App\Services\Audit\JournalAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// §8.1, §8.3, E4 : constitution/annotation du corpus OCR. Isole des
// proces_verbaux de production (§10) - stockage sur le disque dedie
// 'corpus', jamais melange avec le disque 'pv'.
class CorpusController extends Controller
{
    public function __construct(private readonly JournalAuditService $journalAudit) {}

    public function index(Request $request)
    {
        $requete = DocumentCorpus::query()->orderByDesc('created_at');

        if ($request->filled('jeu')) {
            $requete->where('jeu', $request->query('jeu'));
        }
        if ($request->filled('type_gabarit')) {
            $requete->where('type_gabarit', $request->query('type_gabarit'));
        }

        return response()->json(
            $requete->withCount('annotations')->get()->map(fn (DocumentCorpus $d) => $this->presenter($d))
        );
    }

    public function show(DocumentCorpus $document)
    {
        $document->load('annotations');

        return response()->json($this->presenter($document) + [
            'annotations' => $document->annotations->map(fn (Annotation $a) => $this->presenterAnnotation($a)),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'fichier' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:15360'],
            'type_gabarit' => ['nullable', 'string', 'max:100'],
            'jeu' => ['nullable', Rule::in(['train', 'val', 'test'])],
            'anonymise' => ['nullable', 'boolean'],
        ]);

        $nomStocke = Str::uuid().'.'.$request->file('fichier')->getClientOriginalExtension();
        $chemin = Storage::disk('corpus')->putFileAs('documents', $request->file('fichier'), $nomStocke);

        $document = DocumentCorpus::create([
            'nom_fichier' => $request->file('fichier')->getClientOriginalName(),
            'chemin_fichier' => $chemin,
            'type_gabarit' => $donnees['type_gabarit'] ?? 'defaut',
            'jeu' => $donnees['jeu'] ?? null,
            'anonymise' => $donnees['anonymise'] ?? true,
            'importe_par_id' => $request->user()->id,
            'created_at' => now(),
        ]);

        $this->journalAudit->enregistrer('corpus.document_importe', $request->user()->id, 'document_corpus', $document->id, [
            'type_gabarit' => $document->type_gabarit,
        ]);

        return response()->json($this->presenter($document), 201);
    }

    public function storeAnnotation(Request $request, DocumentCorpus $document)
    {
        $donnees = $request->validate([
            'champ' => ['required', 'string', 'max:100'],
            'valeur_verite_terrain' => ['required', 'string'],
            'coordonnees_zone' => ['required', 'array'],
            'coordonnees_zone.x' => ['required', 'integer'],
            'coordonnees_zone.y' => ['required', 'integer'],
            'coordonnees_zone.largeur' => ['required', 'integer'],
            'coordonnees_zone.hauteur' => ['required', 'integer'],
            'ordre_annotation' => ['nullable', 'integer', Rule::in([1, 2])],
        ]);

        $ordre = $donnees['ordre_annotation'] ?? 1;

        if (Annotation::where('document_id', $document->id)->where('champ', $donnees['champ'])->where('ordre_annotation', $ordre)->exists()) {
            return response()->json(['message' => "Une annotation existe deja pour ce champ a l'ordre {$ordre}."], 422);
        }

        $annotation = Annotation::create([
            'document_id' => $document->id,
            'champ' => $donnees['champ'],
            'valeur_verite_terrain' => $donnees['valeur_verite_terrain'],
            'coordonnees_zone' => $donnees['coordonnees_zone'],
            'annotateur_id' => $request->user()->id,
            'ordre_annotation' => $ordre,
            'statut_verification' => 'en_attente',
            'created_at' => now(),
        ]);

        $this->journalAudit->enregistrer('corpus.annotation_creee', $request->user()->id, 'document_corpus', $document->id, [
            'champ' => $donnees['champ'], 'ordre_annotation' => $ordre,
        ]);

        return response()->json($this->presenterAnnotation($annotation), 201);
    }

    /**
     * Split train/val/test (§8.1 etape 4) : ~70/15/15, sans chevauchement
     * de documents entre jeux (assignation au niveau document, jamais au
     * niveau annotation individuelle).
     */
    public function repartir(Request $request)
    {
        $documents = DocumentCorpus::whereNull('jeu')->pluck('id')->shuffle()->values();

        $total = $documents->count();
        if ($total === 0) {
            return response()->json(['message' => 'Aucun document sans jeu assigne.', 'repartis' => 0]);
        }

        $nTrain = (int) round($total * 0.70);
        $nVal = (int) round($total * 0.15);

        $lots = [
            'train' => $documents->slice(0, $nTrain),
            'val' => $documents->slice($nTrain, $nVal),
            'test' => $documents->slice($nTrain + $nVal),
        ];

        foreach ($lots as $jeu => $ids) {
            DocumentCorpus::whereIn('id', $ids)->update(['jeu' => $jeu]);
        }

        $this->journalAudit->enregistrer('corpus.repartition', $request->user()->id, 'document_corpus', null, [
            'total' => $total,
            'train' => $lots['train']->count(),
            'val' => $lots['val']->count(),
            'test' => $lots['test']->count(),
        ]);

        return response()->json([
            'repartis' => $total,
            'train' => $lots['train']->count(),
            'val' => $lots['val']->count(),
            'test' => $lots['test']->count(),
        ]);
    }

    private function presenter(DocumentCorpus $document): array
    {
        return [
            'id' => $document->id,
            'nom_fichier' => $document->nom_fichier,
            'type_gabarit' => $document->type_gabarit,
            'jeu' => $document->jeu,
            'anonymise' => $document->anonymise,
            'nombre_annotations' => $document->annotations_count ?? null,
            'created_at' => $document->created_at,
        ];
    }

    private function presenterAnnotation(Annotation $annotation): array
    {
        return [
            'id' => $annotation->id,
            'champ' => $annotation->champ,
            'valeur_verite_terrain' => $annotation->valeur_verite_terrain,
            'coordonnees_zone' => $annotation->coordonnees_zone,
            'ordre_annotation' => $annotation->ordre_annotation,
            'statut_verification' => $annotation->statut_verification,
        ];
    }
}
