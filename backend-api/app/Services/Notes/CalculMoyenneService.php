<?php

namespace App\Services\Notes;

use App\Models\Etudiant;
use App\Models\Note;

// §7.6 : calcul automatique des moyennes - la moyenne pondérée par
// coefficient élimine par construction les erreurs de calcul manuel (§7.6
// "détection d'erreurs de calcul" : automatiser le calcul EST la mesure de
// prévention, pas un sous-système de vérification séparé).
//
// Seules les notes 'valide' (PV integre, §9.1) entrent dans le calcul :
// une note encore en verification/correction n'est pas officielle.
class CalculMoyenneService
{
    public function calculer(Etudiant $etudiant, string $semestre, string $anneeAcademique): array
    {
        $notes = Note::where('etudiant_id', $etudiant->id)
            ->where('etat_validation', 'valide')
            ->whereHas('pv', fn ($q) => $q->where('semestre', $semestre)->where('annee_academique', $anneeAcademique))
            ->with('pv')
            ->get();

        $sommeCoefficients = $notes->sum('coefficient');
        $sommePonderee = $notes->sum(fn (Note $n) => $n->valeur * $n->coefficient);

        return [
            'etudiant_id' => $etudiant->id,
            'semestre' => $semestre,
            'annee_academique' => $anneeAcademique,
            'moyenne' => $sommeCoefficients > 0 ? round($sommePonderee / $sommeCoefficients, 2) : null,
            'nombre_modules' => $notes->count(),
            'detail' => $notes->map(fn (Note $n) => [
                'code_matiere' => $n->pv->code_matiere,
                'valeur' => $n->valeur,
                'coefficient' => $n->coefficient,
                'motif_penalite' => $n->motif_penalite,
            ])->values(),
        ];
    }
}
