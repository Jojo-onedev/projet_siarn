<?php

namespace App\Console\Commands;

use App\Models\Alerte;
use App\Models\HistoriqueTransitionPv;
use App\Models\ProcesVerbal;
use Illuminate\Console\Command;

// §9.2 : surveillance des delais de traitement (SLA), escalade en cas de
// retard. A executer periodiquement (cron/scheduler, hors scope docker-compose
// dev - a wirer en deploiement : `php artisan schedule:run` toutes les minutes).
//
// Simplification signalee : utilise un delai plancher unique
// (config('siarn.sla.delai_defaut_heures')) plutot que la configuration par
// filiere/etape de workflow_etapes (table presente en base depuis E0 mais pas
// encore reliee a un pilotage utilisateur - hors perimetre de cette iteration).
class VerifierSlaPv extends Command
{
    protected $signature = 'siarn:verifier-sla';

    protected $description = "Detecte les PV dont le delai de traitement depasse le SLA et cree une alerte (§9.2)";

    private const STATUTS_NON_TERMINAUX = ['soumis', 'en_traitement', 'en_verification', 'en_validation', 'complement_requis'];

    public function handle(): int
    {
        $delaiHeures = config('siarn.sla.delai_defaut_heures');
        $seuil = now()->subHours($delaiHeures);
        $nombreAlertes = 0;

        $pvEnCours = ProcesVerbal::whereIn('statut', self::STATUTS_NON_TERMINAUX)->get();

        foreach ($pvEnCours as $pv) {
            $derniereTransition = HistoriqueTransitionPv::where('pv_id', $pv->id)->latest('date_heure')->first();
            $depuis = $derniereTransition?->date_heure ?? $pv->created_at;

            if ($depuis->greaterThan($seuil)) {
                continue;
            }

            $dejaAlerte = Alerte::where('pv_id', $pv->id)->where('niveau', 'avertissement')
                ->where('date_creation', '>=', $depuis)
                ->exists();

            if ($dejaAlerte) {
                continue;
            }

            Alerte::create([
                'pv_id' => $pv->id,
                'niveau' => 'avertissement',
                'message' => sprintf(
                    "PV '%s' bloque en statut '%s' depuis plus de %dh (SLA depasse).",
                    $pv->nom_fichier, $pv->statut->value, $delaiHeures,
                ),
                'destinataire_id' => $pv->depose_par_id,
                'statut_lecture' => false,
                'date_creation' => now(),
            ]);
            $nombreAlertes++;
        }

        $this->info("{$nombreAlertes} alerte(s) SLA creee(s) sur {$pvEnCours->count()} PV en cours.");

        return self::SUCCESS;
    }
}
