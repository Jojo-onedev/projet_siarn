<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Source de verite : infra/db/migrations/0005_workflow.sql
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(config('siarn.sql_migrations_dir') . '/0005_workflow.sql'));
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS historique_transitions_pv CASCADE;
            DROP TABLE IF EXISTS alertes CASCADE;
            DROP TABLE IF EXISTS decisions CASCADE;
            DROP TABLE IF EXISTS workflow_etapes CASCADE;
        SQL);
    }
};
