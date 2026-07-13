<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\JournalAudit;
use Illuminate\Http\Request;

// §7.9, §13.5, E11 : consultation de la piste d'audit (UC-10). §5 RBAC :
// "Consulter piste d'audit globale" -> Admin + Directeur uniquement.
// Lecture seule stricte : journal_audit est append-only en base (0007_audit.sql),
// aucune methode d'ecriture/modification n'est exposee ici ni ailleurs cote API.
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $requete = JournalAudit::with('acteur')->orderByDesc('date_heure');

        if ($request->filled('action')) {
            $requete->where('action', 'ilike', '%'.$request->query('action').'%');
        }
        if ($request->filled('acteur_id')) {
            $requete->where('acteur_id', $request->query('acteur_id'));
        }
        if ($request->filled('cible_type')) {
            $requete->where('cible_type', $request->query('cible_type'));
        }
        if ($request->filled('cible_id')) {
            $requete->where('cible_id', $request->query('cible_id'));
        }
        if ($request->filled('date_debut')) {
            $requete->where('date_heure', '>=', $request->query('date_debut'));
        }
        if ($request->filled('date_fin')) {
            $requete->where('date_heure', '<=', $request->query('date_fin'));
        }

        $page = $requete->paginate((int) $request->query('par_page', 50));

        return response()->json([
            'donnees' => collect($page->items())->map(fn (JournalAudit $j) => $this->presenter($j)),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'dernieres_pages' => $page->lastPage(),
        ]);
    }

    private function presenter(JournalAudit $entree): array
    {
        return [
            'id' => $entree->id,
            'action' => $entree->action,
            'acteur' => $entree->acteur ? [
                'id' => $entree->acteur->id, 'nom' => $entree->acteur->nom, 'prenom' => $entree->acteur->prenom, 'role' => $entree->acteur->role->value,
            ] : null,
            'cible_type' => $entree->cible_type,
            'cible_id' => $entree->cible_id,
            'details' => $entree->details_json,
            'adresse_ip' => $entree->adresse_ip,
            'date_heure' => $entree->date_heure,
        ];
    }
}
