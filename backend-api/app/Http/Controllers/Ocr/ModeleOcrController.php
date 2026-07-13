<?php

namespace App\Http\Controllers\Ocr;

use App\Http\Controllers\Controller;
use App\Models\ModeleOcr;

// §7.8, §8.3 : consultation des versions du modele OCR (historique, CER/WER,
// statut). RBAC §5 : "Entrainer/evaluer le modele OCR" -> Admin (dev)
// uniquement - la promotion candidat->actif reste une operation "dev" via
// ocr-service/training/scripts/versionnement.py (CLI), pas exposee ici en
// ecriture : ce controleur est volontairement lecture seule.
class ModeleOcrController extends Controller
{
    public function index()
    {
        return response()->json(
            ModeleOcr::orderByDesc('date_entrainement')->get()->map(fn (ModeleOcr $m) => $this->presenter($m))
        );
    }

    private function presenter(ModeleOcr $modele): array
    {
        return [
            'id' => $modele->id,
            'version' => $modele->version,
            'date_entrainement' => $modele->date_entrainement,
            'cer' => $modele->cer,
            'wer' => $modele->wer,
            'taille_corpus_train' => $modele->taille_corpus_train,
            'taille_corpus_val' => $modele->taille_corpus_val,
            'taille_corpus_test' => $modele->taille_corpus_test,
            'statut' => $modele->statut,
            'notes' => $modele->notes,
        ];
    }
}
