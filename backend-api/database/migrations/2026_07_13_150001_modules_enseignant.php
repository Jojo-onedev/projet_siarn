<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Source de verite : infra/db/migrations/0010_workflow_validation.sql
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(config('siarn.sql_migrations_dir').'/0010_workflow_validation.sql'));
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE modules DROP COLUMN IF EXISTS enseignant_id;');
    }
};
