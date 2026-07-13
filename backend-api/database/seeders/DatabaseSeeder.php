<?php

namespace Database\Seeders;

use App\Models\Utilisateur;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Compte administrateur initial pour le developpement local uniquement
     * (§5 RBAC : seul Admin peut ensuite provisionner les autres comptes via
     * POST /api/utilisateurs). Mot de passe a changer immediatement hors dev.
     */
    public function run(): void
    {
        Utilisateur::firstOrCreate(
            ['email' => 'admin@siarn.local'],
            [
                'nom' => 'Admin',
                'prenom' => 'SIARN',
                'mot_de_passe_hash' => Hash::make('ChangeMoi123!'),
                'role' => 'admin',
                'statut_mfa' => false,
                'actif' => true,
            ]
        );
    }
}
