<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Point d'entree unique pour ecrire dans journal_audit (§7.9, §13.5) : toute
// action sensible doit y passer, jamais d'ecriture directe eparpillee dans
// les controleurs/services (append-only, cf. 0007_audit.sql - DB::table()
// suffit ici, aucun modele Eloquent necessaire pour une table insert-only).
class JournalAuditService
{
    public function enregistrer(
        string $action,
        ?string $acteurId,
        string $cibleType,
        ?string $cibleId,
        array $details = [],
    ): void {
        DB::table('journal_audit')->insert([
            'id' => (string) Str::uuid(),
            'action' => $action,
            'acteur_id' => $acteurId,
            'cible_type' => $cibleType,
            'cible_id' => $cibleId,
            'details_json' => json_encode($details),
            'date_heure' => now(),
        ]);
    }
}
