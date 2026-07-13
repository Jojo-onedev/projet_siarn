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

    // Microservice OCR (§11.1) : prétraitement OpenCV + segmentation (E3),
    // inférence (E6). backend-api l'appelle en HTTP, jamais d'accès disque
    // partagé direct (isolation des microservices).
    'ocr_service' => [
        'url' => env('OCR_SERVICE_URL', 'http://ocr-service:8000'),
        'timeout_secondes' => (int) env('OCR_SERVICE_TIMEOUT', 30),
    ],

    // Import de PV (§7.3, §13.4) : types/taille acceptés côté import de lots.
    'import_pv' => [
        'extensions_autorisees' => ['jpg', 'jpeg', 'png'],
        'taille_max_ko' => (int) env('PV_TAILLE_MAX_KO', 15360), // 15 Mo
    ],

    // Extraction OCR (§8.3, E6). Seuil plancher (pas le seuil de vérification
    // par champ, déjà géré côté ocr-service) : en dessous, l'extraction est
    // jugée totalement inexploitable -> 'erreur_extraction' plutôt que
    // 'en_verification' (§9.1). Volontairement bas : le doute profite à la
    // vérification humaine, jamais à un rejet automatique injustifié.
    'extraction' => [
        'seuil_confiance_minimum' => (float) env('OCR_SEUIL_CONFIANCE_MINIMUM', 0.05),
    ],

    // Règles automatiques de pénalité (§7.6, E8) : seuil configurable de
    // cumul d'absence non justifiée déclenchant un 00/20 automatique sur le
    // module concerné.
    'penalite' => [
        'seuil_absence_heures' => (float) env('SEUIL_ABSENCE_HEURES', 8),
    ],

    // SLA (§9.2) : délai par défaut (heures) appliqué à une étape de workflow
    // sans configuration explicite dans workflow_etapes.
    'sla' => [
        'delai_defaut_heures' => (int) env('SLA_DELAI_DEFAUT_HEURES', 72),
    ],

    // §13.4, E11 : securite transverse.
    'securite' => [
        // Limite anti brute-force par IP sur /auth/connexion et /auth/mfa/verifier.
        // Valeur test (phpunit.xml) largement relevee : la suite genere plus
        // de requetes/minute qu'un usage reel depuis une seule IP.
        'limite_connexion_par_minute' => (int) env('AUTH_LIMITE_CONNEXION_PAR_MINUTE', 10),
    ],
];
