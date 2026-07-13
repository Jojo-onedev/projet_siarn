-- 0004_pv_notes_absences.sql
-- SIARN — Procès-verbaux, notes, absences
-- PRD refs : §7.3, §7.6, §9, §10

-- modele_ocr référencé ici mais défini en 0006 (corpus/OCR) : on crée d'abord
-- une table "coquille" minimale n'est pas possible en SQL simple, donc l'ordre
-- réel de création est : 0006 doit être appliqué AVANT la contrainte FK ci-dessous.
-- Pour garder des migrations linéaires et lisibles, la FK modele_ocr_id est
-- ajoutée en 0006 via ALTER TABLE une fois modeles_ocr créée (voir ce fichier).

CREATE TABLE proces_verbaux (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nom_fichier         VARCHAR(255) NOT NULL,
    chemin_fichier      TEXT NOT NULL,
    hash_fichier        VARCHAR(128), -- intégrité (ex: SHA-256) du scan original
    code_matiere        VARCHAR(30) NOT NULL,
    module_id           UUID REFERENCES modules(id) ON DELETE SET NULL,
    filiere_id          UUID NOT NULL REFERENCES filieres(id) ON DELETE RESTRICT,
    semestre            VARCHAR(10) NOT NULL,
    annee_academique    VARCHAR(9) NOT NULL,
    date_scan           TIMESTAMPTZ,
    -- Machine à états explicite (§9.1) : jamais un booléen ou un enum ad hoc.
    statut              statut_pv NOT NULL DEFAULT 'soumis',
    depose_par_id       UUID NOT NULL REFERENCES utilisateurs(id) ON DELETE RESTRICT,
    modele_ocr_id       UUID, -- FK ajoutée en 0006 vers modeles_ocr(id)
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TRIGGER trg_pv_updated_at
    BEFORE UPDATE ON proces_verbaux
    FOR EACH ROW EXECUTE FUNCTION maj_updated_at();

CREATE INDEX idx_pv_statut ON proces_verbaux(statut);
CREATE INDEX idx_pv_filiere ON proces_verbaux(filiere_id);
CREATE INDEX idx_pv_depose_par ON proces_verbaux(depose_par_id);

CREATE TABLE notes (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    etudiant_id         UUID NOT NULL REFERENCES etudiants(id) ON DELETE RESTRICT,
    pv_id               UUID NOT NULL REFERENCES proces_verbaux(id) ON DELETE RESTRICT,
    valeur              NUMERIC(4,2) NOT NULL CHECK (valeur >= 0 AND valeur <= 20),
    coefficient         NUMERIC(4,2) NOT NULL DEFAULT 1,
    credit              NUMERIC(4,2) NOT NULL DEFAULT 0,
    etat_validation     etat_validation_note NOT NULL DEFAULT 'extrait_ocr',
    -- Trace explicitement pourquoi une note 00/20 a été attribuée automatiquement
    -- (précision de conception §10), pour la distinguer d'une évaluation normale.
    motif_penalite      motif_penalite_note,
    motif_penalite_detail TEXT,
    score_confiance_ocr NUMERIC(5,2), -- % de confiance du champ extrait (§7.3)
    cree_par_id         UUID REFERENCES utilisateurs(id) ON DELETE SET NULL, -- NULL si créée par le moteur OCR
    valide_par_id       UUID REFERENCES utilisateurs(id) ON DELETE SET NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_note_etudiant_pv UNIQUE (etudiant_id, pv_id),
    -- Une pénalité automatique doit toujours porter un motif (cohérence §7.6)
    CONSTRAINT chk_penalite_coherente CHECK (
        (motif_penalite IS NULL) OR (motif_penalite IS NOT NULL AND valeur = 0)
    )
);

CREATE TRIGGER trg_notes_updated_at
    BEFORE UPDATE ON notes
    FOR EACH ROW EXECUTE FUNCTION maj_updated_at();

CREATE INDEX idx_notes_etudiant ON notes(etudiant_id);
CREATE INDEX idx_notes_pv ON notes(pv_id);
CREATE INDEX idx_notes_etat_validation ON notes(etat_validation);

CREATE TABLE absences (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    etudiant_id     UUID NOT NULL REFERENCES etudiants(id) ON DELETE RESTRICT,
    module_id       UUID NOT NULL REFERENCES modules(id) ON DELETE RESTRICT,
    duree_heures    NUMERIC(5,2) NOT NULL CHECK (duree_heures > 0),
    date            DATE NOT NULL,
    justifiee       BOOLEAN NOT NULL DEFAULT false,
    declare_par_id  UUID REFERENCES utilisateurs(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_absences_etudiant_module ON absences(etudiant_id, module_id);
-- Utilisé par la règle §7.6 : cumul >= seuil (configurable) d'absence non
-- justifiée sur un module -> pénalité automatique 00/20.
