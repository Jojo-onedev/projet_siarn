<?php

return [
    // Repertoire des migrations SQL brutes, source de verite du schema (§10 du
    // PRD). Chemin relatif a backend-api/ en execution hote, ou chemin absolu
    // du volume monte en conteneur (docker-compose.yml).
    'sql_migrations_dir' => env('SQL_MIGRATIONS_DIR', base_path('../infra/db/migrations')),

    // Authentification JWT (§7.1, §13.1). Secret distinct de APP_KEY : la
    // rotation/compromission de l'un ne doit pas affecter l'autre.
    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 60),
        'ttl_mfa_minutes' => (int) env('JWT_TTL_MFA_MINUTES', 5),
    ],

    // Verrouillage de compte anti brute-force (§13.1).
    'verrouillage' => [
        'tentatives_max' => (int) env('AUTH_TENTATIVES_MAX', 5),
        'duree_minutes' => (int) env('AUTH_VERROUILLAGE_MINUTES', 15),
    ],

    'mfa' => [
        'emetteur' => env('MFA_EMETTEUR', 'SIARN'),
    ],
];
