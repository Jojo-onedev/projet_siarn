<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Source de verite : infra/db/migrations/0008_pv_pretraitement.sql
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(config('siarn.sql_migrations_dir').'/0008_pv_pretraitement.sql'));
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE proces_verbaux
                DROP COLUMN IF EXISTS type_gabarit,
                DROP COLUMN IF EXISTS chemin_image_pretraitee,
                DROP COLUMN IF EXISTS zones_segmentees;
        SQL);
    }
};
