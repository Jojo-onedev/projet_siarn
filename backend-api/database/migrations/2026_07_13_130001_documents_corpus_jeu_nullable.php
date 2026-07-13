<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Correction : documents_corpus.jeu etait NOT NULL, incompatible avec le
// workflow reel (import du document d'abord, assignation train/val/test
// ensuite via CorpusController::repartir, §8.1 etape 4). Corrige aussi a la
// source (infra/db/migrations/0006_corpus_ocr.sql) pour les installations fraiches.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('ALTER TABLE documents_corpus ALTER COLUMN jeu DROP NOT NULL;');
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE documents_corpus ALTER COLUMN jeu SET NOT NULL;');
    }
};
