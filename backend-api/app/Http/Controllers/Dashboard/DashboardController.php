<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\Alerte;
use App\Models\Filiere;
use App\Models\HistoriqueTransitionPv;
use App\Models\ModeleOcr;
use App\Models\ProcesVerbal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

// §7.8, E10 : tableaux de bord et reporting. §5 RBAC : "Tableaux de bord
// filiere" -> Chef de departement (sa filiere uniquement) + Responsable
// academique (toutes) + Directeur (toutes) - PAS Agent scolarite ni Admin,
// exclus explicitement par la matrice pour cette fonctionnalite precise.
class DashboardController extends Controller
{
    public function pv(Request $request)
    {
        $requete = $this->requeteFiltree($request);

        $parStatut = (clone $requete)->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        $pvsPublies = (clone $requete)->where('statut', 'publie')->get(['id', 'created_at']);
        $delaiMoyenHeures = $this->calculerDelaiMoyenHeures($pvsPublies);

        $idsEnScope = (clone $requete)->pluck('id');
        $alertesNonLues = Alerte::whereIn('pv_id', $idsEnScope)->where('statut_lecture', false)->count();

        return response()->json([
            'total_pv' => (clone $requete)->count(),
            'par_statut' => $parStatut,
            'delai_moyen_traitement_heures' => $delaiMoyenHeures,
            'alertes_sla_non_lues' => $alertesNonLues,
        ]);
    }

    public function ocr()
    {
        $modeles = ModeleOcr::orderByDesc('date_entrainement')->get(['id', 'version', 'cer', 'wer', 'statut', 'date_entrainement']);

        $confiances = ProcesVerbal::whereNotNull('champs_extraits')
            ->pluck('champs_extraits')
            ->flatten(1)
            ->pluck('score_confiance')
            ->filter(fn ($v) => $v !== null);

        return response()->json([
            'modeles' => $modeles,
            'confiance_moyenne_production' => $confiances->isNotEmpty() ? round($confiances->avg(), 4) : null,
            'nombre_extractions_analysees' => $confiances->count(),
        ]);
    }

    public function exporterPv(Request $request): Response
    {
        $pvs = $this->requeteFiltree($request)->with('filiere')->get();

        $lignes = "nom_fichier,code_matiere,filiere,semestre,annee_academique,statut,date_import\n";
        foreach ($pvs as $pv) {
            $lignes .= implode(',', [
                '"'.str_replace('"', '""', $pv->nom_fichier).'"',
                $pv->code_matiere,
                '"'.str_replace('"', '""', $pv->filiere->nom ?? '').'"',
                $pv->semestre,
                $pv->annee_academique,
                $pv->statut->value,
                $pv->created_at->toDateTimeString(),
            ])."\n";
        }

        return response($lignes, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pv_export.csv"',
        ]);
    }

    private function requeteFiltree(Request $request)
    {
        $requete = ProcesVerbal::query();
        $filiereId = $this->resoudrePerimetreFiliere($request);

        if ($filiereId) {
            $requete->where('filiere_id', $filiereId);
        }
        if ($request->filled('semestre')) {
            $requete->where('semestre', $request->query('semestre'));
        }
        if ($request->filled('annee_academique')) {
            $requete->where('annee_academique', $request->query('annee_academique'));
        }

        return $requete;
    }

    /**
     * Un chef de departement est TOUJOURS restreint a sa propre filiere
     * (§5), quel que soit le filtre demande - jamais une simple option
     * cote client, verifie ici cote serveur.
     */
    private function resoudrePerimetreFiliere(Request $request): ?string
    {
        $acteur = $request->user();

        if ($acteur->role === RoleUtilisateur::ChefDepartement) {
            $filiere = Filiere::where('chef_departement_id', $acteur->id)->first();

            return $filiere?->id ?? '__aucune__';
        }

        return $request->query('filiere_id');
    }

    private function calculerDelaiMoyenHeures(Collection $pvsPublies): ?float
    {
        if ($pvsPublies->isEmpty()) {
            return null;
        }

        $transitions = HistoriqueTransitionPv::whereIn('pv_id', $pvsPublies->pluck('id'))
            ->where('nouveau_statut', 'publie')
            ->get()
            ->keyBy('pv_id');

        $delais = $pvsPublies->map(function (ProcesVerbal $pv) use ($transitions) {
            $transition = $transitions->get($pv->id);

            return $transition ? $pv->created_at->diffInHours($transition->date_heure) : null;
        })->filter(fn ($v) => $v !== null);

        return $delais->isNotEmpty() ? round($delais->avg(), 1) : null;
    }
}
