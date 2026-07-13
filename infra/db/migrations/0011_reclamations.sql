-- 0011_reclamations.sql
-- SIARN — Réclamations étudiantes (E9)
-- PRD refs : §7.7, UC-07, §5 RBAC ("Initier réclamation" -> Étudiant uniquement)
--
-- Entité absente du tableau §10 (comme "modules" en E0) mais explicitement
-- requise par le PRD (UC-07, module 7.7 "Gestion des réclamations
-- étudiants") : précision de conception, pas un ajout hors périmètre.

CREATE TYPE statut_reclamation AS ENUM (
    'ouverte',
    'en_traitement',
    'resolue',
    'rejetee'
);

CREATE TABLE reclamations (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    etudiant_id         UUID NOT NULL REFERENCES etudiants(id) ON DELETE RESTRICT,
    -- Nullable : une reclamation peut porter sur une note precise ou etre
    -- plus generale (erreur de filiere, absence non comptabilisee, etc.).
    note_id             UUID REFERENCES notes(id) ON DELETE SET NULL,
    motif               TEXT NOT NULL,
    statut              statut_reclamation NOT NULL DEFAULT 'ouverte',
    reponse             TEXT,
    traite_par_id       UUID REFERENCES utilisateurs(id) ON DELETE SET NULL,
    date_creation       TIMESTAMPTZ NOT NULL DEFAULT now(),
    date_traitement     TIMESTAMPTZ
);

CREATE INDEX idx_reclamations_etudiant ON reclamations(etudiant_id);
CREATE INDEX idx_reclamations_statut ON reclamations(statut);
