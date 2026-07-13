<?php

return [
    // Repertoire des migrations SQL brutes, source de verite du schema (§10 du
    // PRD). Chemin relatif a backend-api/ en execution hote, ou chemin absolu
    // du volume monte en conteneur (docker-compose.yml).
    'sql_migrations_dir' => env('SQL_MIGRATIONS_DIR', base_path('../infra/db/migrations')),
];
