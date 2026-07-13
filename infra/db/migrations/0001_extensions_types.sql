-- 0001_extensions_types.sql
-- SIARN — Extensions PostgreSQL et types énumérés partagés
-- PRD refs : §10 (modèle de données), §9.1 (machine à états du PV), §5 (RBAC)

-- pgcrypto : génération d'UUID (gen_random_uuid) pour des identifiants non séquentiels
-- (évite l'énumération d'IDs côté API, cf. §13.4 sécurité applicative)
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- citext : emails insensibles à la casse au niveau contrainte d'unicité
CREATE EXTENSION IF NOT EXISTS citext;

-- Rôles applicatifs (matrice RBAC §5). Un utilisateur porte un seul rôle
-- (colonne role sur utilisateurs, cf. 0002). La configuration "1 responsable
-- académique cumulant les 3 chefs de département" (§4) ne nécessite donc PAS
-- une table de rôles multiples : elle repose uniquement sur (a) le rôle
-- 'responsable_academique', dont le périmètre couvre les 3 filières par
-- construction RBAC, et (b) Filiere.chef_departement_id nullable (0003).
CREATE TYPE role_utilisateur AS ENUM (
    'agent_scolarite',
    'enseignant',
    'chef_departement',
    'responsable_academique',
    'etudiant',
    'admin',
    'directeur'
);

-- Machine à états du dossier PV — §9.1 du PRD, recopiée telle quelle.
-- Modélisée explicitement en type énuméré + table d'historique (0005),
-- jamais déduite d'un booléen.
CREATE TYPE statut_pv AS ENUM (
    'soumis',
    'en_traitement',
    'erreur_extraction',
    'en_verification',
    'en_validation',
    'complement_requis',
    'valide',
    'integre',
    'publie',
    'rejete',
    'archive'
);

-- État de validation d'une note individuelle (distinct du statut du PV global :
-- un PV peut être "en_verification" pendant que certaines notes sont déjà "corrigees").
CREATE TYPE etat_validation_note AS ENUM (
    'extrait_ocr',
    'corrige',
    'valide',
    'rejete'
);

-- §7.6 : motif explicite d'une note 00/20 automatique, pour la distinguer
-- d'une évaluation normale (précision de conception §10).
CREATE TYPE motif_penalite_note AS ENUM (
    'fraude',
    'absence_non_justifiee'
);

CREATE TYPE type_decision AS ENUM (
    'valider',
    'rejeter',
    'complement_requis'
);

CREATE TYPE niveau_alerte AS ENUM (
    'info',
    'avertissement',
    'critique'
);

-- §8.1 étape 4 : split train/val/test du corpus OCR, disjoint par document.
CREATE TYPE jeu_corpus AS ENUM (
    'train',
    'val',
    'test'
);

CREATE TYPE statut_modele_ocr AS ENUM (
    'en_entrainement',
    'candidat',
    'actif',
    'archive'
);

-- Fonction utilitaire commune : maintien automatique de updated_at
CREATE OR REPLACE FUNCTION maj_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
