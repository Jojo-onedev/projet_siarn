<?php

namespace App\StateMachines;

use App\Enums\StatutPv;
use App\Exceptions\TransitionInvalideException;
use App\Models\HistoriqueTransitionPv;
use App\Models\ProcesVerbal;
use App\Models\Utilisateur;
use App\Services\Audit\JournalAuditService;
use Illuminate\Support\Facades\DB;

// Machine a etats explicite du dossier PV — §9.1 du PRD, recopiee telle
// quelle depuis le circuit nominal :
// Soumis -> En traitement -> En verification -> En validation -> Valide
// -> Integre -> Publie -> Archive (+ branches Erreur d'extraction, Rejete,
// Complement requis).
//
// Regle non negociable : jamais de transition en modifiant directement
// $pv->statut ; toujours passer par transitionner(), qui refuse les
// transitions non prevues et journalise systematiquement (historique
// dedie + journal_audit).
class MachineEtatsPv
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'soumis' => ['en_traitement'],
        'en_traitement' => ['en_verification', 'erreur_extraction'],
        'erreur_extraction' => ['soumis'],
        'en_verification' => ['en_validation', 'complement_requis'],
        'en_validation' => ['valide', 'rejete', 'complement_requis'],
        'complement_requis' => ['en_verification'],
        'valide' => ['integre'],
        'integre' => ['publie'],
        'publie' => ['archive'],
        'rejete' => ['archive'],
    ];

    public function __construct(private readonly JournalAuditService $journalAudit) {}

    public function transitionner(
        ProcesVerbal $pv,
        StatutPv $nouveauStatut,
        ?Utilisateur $acteur,
        ?string $motif = null,
        array $attributsSupplementaires = [],
    ): ProcesVerbal {
        $ancienStatut = $pv->statut;

        $transitionsAutorisees = self::TRANSITIONS[$ancienStatut->value] ?? [];
        if (! in_array($nouveauStatut->value, $transitionsAutorisees, true)) {
            throw new TransitionInvalideException(
                "Transition refusee : {$ancienStatut->value} -> {$nouveauStatut->value}"
            );
        }

        DB::transaction(function () use ($pv, $ancienStatut, $nouveauStatut, $acteur, $motif, $attributsSupplementaires) {
            $pv->update(array_merge($attributsSupplementaires, ['statut' => $nouveauStatut]));

            HistoriqueTransitionPv::create([
                'pv_id' => $pv->id,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => $nouveauStatut,
                'acteur_id' => $acteur?->id,
                'motif' => $motif,
                'date_heure' => now(),
            ]);

            $this->journalAudit->enregistrer(
                'pv.transition',
                $acteur?->id,
                'proces_verbal',
                $pv->id,
                ['ancien_statut' => $ancienStatut->value, 'nouveau_statut' => $nouveauStatut->value, 'motif' => $motif],
            );
        });

        return $pv->fresh();
    }

    /** Insertion initiale d'un PV en statut 'soumis' : pas une transition (pas d'ancien_statut). */
    public function enregistrerCreation(ProcesVerbal $pv, ?Utilisateur $acteur): void
    {
        HistoriqueTransitionPv::create([
            'pv_id' => $pv->id,
            'ancien_statut' => null,
            'nouveau_statut' => StatutPv::Soumis,
            'acteur_id' => $acteur?->id,
            'motif' => null,
            'date_heure' => now(),
        ]);

        $this->journalAudit->enregistrer('pv.import', $acteur?->id, 'proces_verbal', $pv->id, ['nom_fichier' => $pv->nom_fichier]);
    }
}
