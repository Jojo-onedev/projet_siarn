-- 0008_pv_pretraitement.sql
-- SIARN — Colonnes de pretraitement/segmentation du PV (E3)
-- PRD refs : §7.3, §9
--
-- Precision de conception (non listee telle quelle au §10, necessaire pour
-- porter le resultat du pretraitement OpenCV + segmentation par zones avant
-- que l'inference OCR reelle (E6, modele entraine en E5) ne soit disponible) :
-- le PV reste en statut 'en_traitement' apres cette etape, la transition vers
-- 'en_verification'/'erreur_extraction' relevant explicitement de l'OCR (E6/E7).

ALTER TABLE proces_verbaux
    ADD COLUMN type_gabarit             VARCHAR(100) DEFAULT 'defaut',
    ADD COLUMN chemin_image_pretraitee  TEXT,
    ADD COLUMN zones_segmentees         JSONB;
