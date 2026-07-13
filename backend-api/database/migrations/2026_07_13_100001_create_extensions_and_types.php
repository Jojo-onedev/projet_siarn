<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Source de verite du schema : infra/db/migrations/0001_extensions_types.sql
// (§10 du PRD). Cette migration Laravel se contente d'executer le SQL brut
// partage, pour que `php artisan migrate` reste l'outil d'orchestration sans
// dupliquer la definition du schema entre Laravel et le microservice OCR.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(config('siarn.sql_migrations_dir') . '/0001_extensions_types.sql'));
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS maj_updated_at CASCADE;
            DROP TYPE IF EXISTS statut_modele_ocr;
            DROP TYPE IF EXISTS jeu_corpus;
            DROP TYPE IF EXISTS niveau_alerte;
            DROP TYPE IF EXISTS type_decision;
            DROP TYPE IF EXISTS motif_penalite_note;
            DROP TYPE IF EXISTS etat_validation_note;
            DROP TYPE IF EXISTS statut_pv;
            DROP TYPE IF EXISTS role_utilisateur;
        SQL);
    }
};
