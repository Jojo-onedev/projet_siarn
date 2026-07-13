<?php

namespace App\Services\Notes;

use App\Models\Absence;
use App\Models\Etudiant;
use App\Models\Module;
use App\Models\Note;
use App\Models\Utilisateur;
use App\Services\Audit\JournalAuditService;

// §7.6 : regles automatiques de notation. Une penalite ecrase la valeur de
// la note (00/20) et trace explicitement le motif (motif_penalite,
// precision de conception §10) - jamais silencieuse.
class ReglesPenaliteService
{
    public function __construct(private readonly JournalAuditService $journalAudit) {}

    public function appliquerPenaliteFraude(Note $note, string $motifDetail, Utilisateur $acteur): Note
    {
        $note->update([
            'valeur' => 0,
            'motif_penalite' => 'fraude',
            'motif_penalite_detail' => $motifDetail,
        ]);

        $this->journalAudit->enregistrer('note.penalite_fraude', $acteur->id, 'note', $note->id, [
            'etudiant_id' => $note->etudiant_id, 'motif' => $motifDetail,
        ]);

        return $note->fresh();
    }

    /**
     * Verifie le cumul d'absence non justifiee pour un etudiant sur un
     * module et applique automatiquement la penalite 00/20 si le seuil
     * configurable (§7.6) est atteint. Sans note existante pour ce couple
     * etudiant/module (aucun PV pas encore integre), ne fait rien : la
     * verification est reappelee lors de la creation de la note (NoteController).
     */
    public function verifierEtAppliquerPenaliteAbsence(Etudiant $etudiant, Module $module, ?Utilisateur $acteur = null): ?Note
    {
        $cumulHeures = Absence::where('etudiant_id', $etudiant->id)
            ->where('module_id', $module->id)
            ->where('justifiee', false)
            ->sum('duree_heures');

        $seuil = config('siarn.penalite.seuil_absence_heures');
        if ($cumulHeures < $seuil) {
            return null;
        }

        $note = Note::whereHas('pv', fn ($q) => $q->where('module_id', $module->id))
            ->where('etudiant_id', $etudiant->id)
            ->whereNull('motif_penalite')
            ->first();

        if (! $note) {
            return null;
        }

        $note->update([
            'valeur' => 0,
            'motif_penalite' => 'absence_non_justifiee',
            'motif_penalite_detail' => sprintf('Cumul %.1fh d\'absence non justifiee (seuil %.1fh)', $cumulHeures, $seuil),
        ]);

        $this->journalAudit->enregistrer('note.penalite_absence', $acteur?->id, 'note', $note->id, [
            'etudiant_id' => $etudiant->id, 'module_id' => $module->id, 'cumul_heures' => $cumulHeures,
        ]);

        return $note->fresh();
    }
}
