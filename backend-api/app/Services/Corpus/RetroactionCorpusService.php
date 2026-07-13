<?php

namespace App\Services\Corpus;

use App\Models\Annotation;
use App\Models\DocumentCorpus;
use App\Models\ProcesVerbal;
use App\Models\Utilisateur;

// Boucle de retroaction §7.5/§8.4 : une correction humaine (E7) sur un champ
// OCR devient une nouvelle annotation du corpus (E4), reutilisable pour un
// futur reentrainement (E5). Isolation §10 preservee : on cree une ENTREE DE
// CORPUS DEDIEE (jamais de FK vers proces_verbaux) ; seul le chemin de
// l'image pretraitee est copie par valeur au moment de l'export.
class RetroactionCorpusService
{
    public function exporterCorrection(ProcesVerbal $pv, string $champ, string $valeurValidee, Utilisateur $acteur): ?Annotation
    {
        if (! $pv->chemin_image_pretraitee) {
            return null;
        }

        $document = DocumentCorpus::firstOrCreate(
            ['chemin_fichier' => $pv->chemin_image_pretraitee],
            [
                'nom_fichier' => $pv->nom_fichier,
                'type_gabarit' => $pv->type_gabarit,
                'jeu' => null,
                'anonymise' => true,
                'date_annotation' => now(),
                'importe_par_id' => $acteur->id,
                'created_at' => now(),
            ]
        );

        $coordonnees = collect($pv->zones_segmentees ?? [])->firstWhere('nom', $champ)
            ?? ['x' => 0, 'y' => 0, 'largeur' => 0, 'hauteur' => 0];

        if (Annotation::where('document_id', $document->id)->where('champ', $champ)->where('ordre_annotation', 1)->exists()) {
            return null;
        }

        return Annotation::create([
            'document_id' => $document->id,
            'champ' => $champ,
            'valeur_verite_terrain' => $valeurValidee,
            'coordonnees_zone' => $coordonnees,
            'annotateur_id' => $acteur->id,
            'ordre_annotation' => 1,
            'statut_verification' => 'concordant',
            'created_at' => now(),
        ]);
    }
}
