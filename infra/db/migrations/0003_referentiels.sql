-- 0003_referentiels.sql
-- SIARN — Filières, modules/matières, étudiants
-- PRD refs : §7.2, §10, §4 (contrainte chef_departement_id nullable)

CREATE TABLE filieres (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nom                     VARCHAR(150) NOT NULL,
    code                    VARCHAR(20) NOT NULL UNIQUE,
    -- NULLABLE par exigence explicite du PRD (§4, §10) : permet aussi bien
    -- 3 chefs de département distincts qu'un unique responsable académique
    -- cumulant les 3 rôles, sans changement de schéma. Quand NULL, la
    -- validation de la filière est portée par tout utilisateur ayant le rôle
    -- 'responsable_academique' (vérifié côté application/RBAC, pas ici).
    chef_departement_id     UUID REFERENCES utilisateurs(id) ON DELETE SET NULL,
    actif                   BOOLEAN NOT NULL DEFAULT true,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TRIGGER trg_filieres_updated_at
    BEFORE UPDATE ON filieres
    FOR EACH ROW EXECUTE FUNCTION maj_updated_at();

-- Ajout non listé littéralement dans le tableau d'entités §10, mais requis
-- pour donner une cible relationnelle à PV.code_matiere et Absence.module_id
-- (tous deux mentionnés dans le PRD sans entité porteuse dédiée). Signalé
-- explicitement comme précision de conception, pas comme périmètre ajouté :
-- aucune fonctionnalité nouvelle, seulement la normalisation d'un champ texte.
CREATE TABLE modules (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code            VARCHAR(30) NOT NULL UNIQUE,
    nom             VARCHAR(200) NOT NULL,
    filiere_id      UUID NOT NULL REFERENCES filieres(id) ON DELETE RESTRICT,
    niveau          VARCHAR(20) NOT NULL,   -- ex: 'L1', 'L2', 'L3', 'M1', 'M2'
    semestre        VARCHAR(10) NOT NULL,   -- ex: 'S1'..'S6'
    coefficient     NUMERIC(4,2) NOT NULL DEFAULT 1,
    credits         NUMERIC(4,2) NOT NULL DEFAULT 0,
    actif           BOOLEAN NOT NULL DEFAULT true,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TRIGGER trg_modules_updated_at
    BEFORE UPDATE ON modules
    FOR EACH ROW EXECUTE FUNCTION maj_updated_at();

CREATE INDEX idx_modules_filiere ON modules(filiere_id);

CREATE TABLE etudiants (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    matricule       VARCHAR(30) NOT NULL UNIQUE,
    nom             VARCHAR(150) NOT NULL,
    prenom          VARCHAR(150) NOT NULL,
    filiere_id      UUID NOT NULL REFERENCES filieres(id) ON DELETE RESTRICT,
    niveau          VARCHAR(20) NOT NULL,
    annee_academique VARCHAR(9) NOT NULL,   -- ex: '2025-2026'
    -- Lien optionnel vers le compte utilisateur du portail étudiant (§7.2, E12).
    -- Nullable tant que l'étudiant n'a pas encore activé son accès au portail.
    utilisateur_id  UUID UNIQUE REFERENCES utilisateurs(id) ON DELETE SET NULL,
    actif           BOOLEAN NOT NULL DEFAULT true,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TRIGGER trg_etudiants_updated_at
    BEFORE UPDATE ON etudiants
    FOR EACH ROW EXECUTE FUNCTION maj_updated_at();

CREATE INDEX idx_etudiants_filiere ON etudiants(filiere_id);
CREATE INDEX idx_etudiants_nom_prenom ON etudiants(nom, prenom); -- recherche multicritère §7.2
