<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Source de verite : infra/db/migrations/0003_referentiels.sql
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(config('siarn.sql_migrations_dir') . '/0003_referentiels.sql'));
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS etudiants CASCADE;
            DROP TABLE IF EXISTS modules CASCADE;
            DROP TABLE IF EXISTS filieres CASCADE;
        SQL);
    }
};
