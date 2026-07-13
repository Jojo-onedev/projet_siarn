<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Source de verite : infra/db/migrations/0006_corpus_ocr.sql
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(config('siarn.sql_migrations_dir') . '/0006_corpus_ocr.sql'));
    }

    public function down(): void
    {
        // DROP ... CASCADE sur modeles_ocr supprime au passage la contrainte
        // fk_pv_modele_ocr ajoutee par ALTER TABLE sur proces_verbaux, sans
        // supprimer proces_verbaux elle-meme.
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS modeles_ocr CASCADE;
            DROP TABLE IF EXISTS annotations CASCADE;
            DROP TABLE IF EXISTS documents_corpus CASCADE;
        SQL);
    }
};
