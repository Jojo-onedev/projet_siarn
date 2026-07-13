<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Source de verite : infra/db/migrations/0002_utilisateurs_auth.sql
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(config('siarn.sql_migrations_dir') . '/0002_utilisateurs_auth.sql'));
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS journal_connexions CASCADE;
            DROP TABLE IF EXISTS sessions_jwt CASCADE;
            DROP TABLE IF EXISTS utilisateurs CASCADE;
        SQL);
    }
};
