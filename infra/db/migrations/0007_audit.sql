-- 0007_audit.sql
-- SIARN — Journal d'audit inviolable (append-only)
-- PRD refs : §7.9, §10, §13.5
--
-- Règle non négociable : append-only dès la première itération, pas de
-- module "à faire plus tard". Deux niveaux de protection cumulés :
--   1. Applicatif : le rôle DB utilisé par le backend Laravel ne doit avoir
--      que GRANT INSERT, SELECT sur journal_audit (jamais UPDATE/DELETE) —
--      à configurer lors du provisioning de la base (infra/db, hors migration
--      de schéma pure).
--   2. Défensif, au niveau base : trigger qui lève une exception même si un
--      rôle disposait par erreur d'un GRANT UPDATE/DELETE (défense en profondeur,
--      y compris contre l'admin applicatif — §7.9 "non modifiables même par
--      l'admin applicatif").

CREATE TABLE journal_audit (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    action          VARCHAR(150) NOT NULL, -- ex: 'pv.transition', 'note.correction', 'utilisateur.connexion'
    acteur_id       UUID REFERENCES utilisateurs(id) ON DELETE SET NULL, -- NULL = action système (moteur OCR, cron SLA)
    cible_type      VARCHAR(100) NOT NULL, -- ex: 'proces_verbal', 'note', 'utilisateur'
    cible_id        UUID,
    details_json    JSONB NOT NULL DEFAULT '{}'::jsonb, -- ancien/nouvel état, motif, champs modifiés, etc.
    adresse_ip      INET,
    user_agent      TEXT,
    date_heure      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_journal_audit_acteur ON journal_audit(acteur_id);
CREATE INDEX idx_journal_audit_cible ON journal_audit(cible_type, cible_id);
CREATE INDEX idx_journal_audit_date ON journal_audit(date_heure);

CREATE OR REPLACE FUNCTION interdire_modification_audit()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'journal_audit est append-only : UPDATE/DELETE interdits (PRD §7.9)';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_journal_audit_append_only
    BEFORE UPDATE OR DELETE ON journal_audit
    FOR EACH ROW EXECUTE FUNCTION interdire_modification_audit();
