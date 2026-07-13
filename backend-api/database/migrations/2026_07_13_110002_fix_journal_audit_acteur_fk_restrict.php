<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Correction : la FK journal_audit.acteur_id etait en ON DELETE SET NULL, ce
// qui declenche un UPDATE en cascade lors de la suppression d'un utilisateur
// - impossible sur une table append-only (le trigger rejette meme les UPDATE
// issus d'une cascade). Passe en ON DELETE RESTRICT : un utilisateur avec un
// historique d'audit ne peut plus etre supprime en dur, seulement desactive.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE journal_audit DROP CONSTRAINT journal_audit_acteur_id_fkey;
            ALTER TABLE journal_audit
                ADD CONSTRAINT journal_audit_acteur_id_fkey
                FOREIGN KEY (acteur_id) REFERENCES utilisateurs(id) ON DELETE RESTRICT;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE journal_audit DROP CONSTRAINT journal_audit_acteur_id_fkey;
            ALTER TABLE journal_audit
                ADD CONSTRAINT journal_audit_acteur_id_fkey
                FOREIGN KEY (acteur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;
        SQL);
    }
};
