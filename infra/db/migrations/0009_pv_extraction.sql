-- 0009_pv_extraction.sql
-- SIARN — Resultats d'extraction OCR + verification humaine (E6/E7)
-- PRD refs : §7.3, §7.5, §8.3, §9
--
-- champs_extraits : tableau JSON, un element par zone/champ extrait par le
-- modele OCR actif (§8.3). Chaque element garde separement la valeur brute
-- proposee par l'OCR (valeur_ocr, immuable - traçabilite §7.9) et la valeur
-- validee par l'agent apres verification humaine (valeur_validee, §7.5) :
-- [{"champ", "valeur_ocr", "score_confiance", "verification_requise",
--   "valeur_validee", "corrige_par_id", "date_verification"}, ...]

ALTER TABLE proces_verbaux
    ADD COLUMN champs_extraits JSONB;
