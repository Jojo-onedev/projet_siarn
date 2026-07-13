<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Correction : secret_mfa etait VARCHAR(255), trop court pour un secret TOTP
// chiffre (Crypt::encryptString produit un payload JSON base64 > 255
// caracteres). Corrige aussi a la source (infra/db/migrations/0002_...sql)
// pour les futures installations fraiches ; cette migration met a niveau les
// bases deja migrees avec l'ancien type.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('ALTER TABLE utilisateurs ALTER COLUMN secret_mfa TYPE TEXT;');
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE utilisateurs ALTER COLUMN secret_mfa TYPE VARCHAR(255);');
    }
};
