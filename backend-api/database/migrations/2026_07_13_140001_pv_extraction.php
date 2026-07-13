<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Source de verite : infra/db/migrations/0009_pv_extraction.sql
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(config('siarn.sql_migrations_dir').'/0009_pv_extraction.sql'));
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE proces_verbaux DROP COLUMN IF EXISTS champs_extraits;');
    }
};
