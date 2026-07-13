-- 0010_workflow_validation.sql
-- SIARN — Association enseignant/module (E8)
-- PRD refs : §4, §5, §7.6
--
-- Precision de conception (comme modules en 0003) : le PRD accorde a
-- l'Enseignant le droit de "verifier son propre PV numerise (lecture +
-- signalement fraude)", ce qui suppose de savoir quel enseignant est
-- responsable de quel module/matiere. Nullable : un module peut ne pas
-- encore avoir d'enseignant affecte dans le referentiel.

ALTER TABLE modules
    ADD COLUMN enseignant_id UUID REFERENCES utilisateurs(id) ON DELETE SET NULL;

CREATE INDEX idx_modules_enseignant ON modules(enseignant_id);
