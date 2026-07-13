-- 0005_workflow.sql
-- SIARN — Workflow paramétrable, décisions, alertes, historique de la machine à états
-- PRD refs : §7.6, §9, §10

CREATE TABLE workflow_etapes (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    -- NULL = étape générique applicable à toutes les filières ; sinon
    -- personnalisation par filière (circuit "paramétrable", §7.6).
    filiere_id          UUID REFERENCES filieres(id) ON DELETE CASCADE,
    nom_etape           VARCHAR(150) NOT NULL,
    ordre               SMALLINT NOT NULL,
    acteur_responsable  role_utilisateur NOT NULL,
    delai_sla_heures    INTEGER NOT NULL, -- §9.2 : surveillance des délais, escalade si dépassé
    actif               BOOLEAN NOT NULL DEFAULT true,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_workflow_etape_ordre UNIQUE (filiere_id, ordre)
);

CREATE TRIGGER trg_workflow_etapes_updated_at
    BEFORE UPDATE ON workflow_etapes
    FOR EACH ROW EXECUTE FUNCTION maj_updated_at();

CREATE TABLE decisions (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pv_id           UUID NOT NULL REFERENCES proces_verbaux(id) ON DELETE RESTRICT,
    type_decision   type_decision NOT NULL,
    -- Motif obligatoire pour un rejet ou une demande de complément (§7.6 :
    -- "Rejet / demande de réexamen avec motif obligatoire").
    motif           TEXT,
    auteur_id       UUID NOT NULL REFERENCES utilisateurs(id) ON DELETE RESTRICT,
    date_decision   TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_motif_obligatoire_si_rejet CHECK (
        type_decision = 'valider' OR (motif IS NOT NULL AND length(trim(motif)) > 0)
    )
);

CREATE INDEX idx_decisions_pv ON decisions(pv_id);
CREATE INDEX idx_decisions_auteur ON decisions(auteur_id);

CREATE TABLE alertes (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pv_id           UUID REFERENCES proces_verbaux(id) ON DELETE CASCADE,
    niveau          niveau_alerte NOT NULL,
    message         TEXT NOT NULL,
    destinataire_id UUID REFERENCES utilisateurs(id) ON DELETE CASCADE,
    statut_lecture  BOOLEAN NOT NULL DEFAULT false,
    date_creation   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_alertes_destinataire ON alertes(destinataire_id, statut_lecture);
CREATE INDEX idx_alertes_pv ON alertes(pv_id);

-- Historique des transitions de la machine à états du PV (§9.1). Distinct du
-- journal d'audit général (0007) : celui-ci trace TOUTE action sensible,
-- celui-là est la source de vérité métier dédiée à "quel PV est passé de quel
-- état à quel état, quand, par qui, pourquoi" — consommée directement par le
-- moteur de workflow (E8) sans avoir à filtrer le journal d'audit générique.
CREATE TABLE historique_transitions_pv (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pv_id           UUID NOT NULL REFERENCES proces_verbaux(id) ON DELETE CASCADE,
    ancien_statut   statut_pv,          -- NULL pour la création initiale ('soumis')
    nouveau_statut  statut_pv NOT NULL,
    acteur_id       UUID REFERENCES utilisateurs(id) ON DELETE SET NULL, -- NULL si transition automatique (moteur OCR, SLA)
    motif           TEXT,
    date_heure      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_historique_transitions_pv ON historique_transitions_pv(pv_id, date_heure);
