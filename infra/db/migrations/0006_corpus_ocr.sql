-- 0006_corpus_ocr.sql
-- SIARN — Corpus d'entraînement OCR, annotations, versions du modèle
-- PRD refs : §8 (pipeline OCR — cœur scientifique), §10
--
-- Isolation stricte vis-à-vis des PV de production (précision de conception
-- §10) : aucune FK entre proces_verbaux et documents_corpus/annotations.
-- Un PV de production peut alimenter le corpus UNIQUEMENT via un export
-- applicatif explicite (boucle de rétroaction §8.4, §7.5), jamais par
-- réutilisation directe de la ligne en base.

CREATE TABLE documents_corpus (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nom_fichier         VARCHAR(255) NOT NULL,
    chemin_fichier      TEXT NOT NULL,
    type_gabarit        VARCHAR(100) NOT NULL, -- ex: 'PV_semestriel_v1', identifie le gabarit documentaire (§8.1 étape 1)
    -- Nullable : un document est importe AVANT d'etre reparti (§8.1 etape 4,
    -- E4 CorpusController::repartir) ; NULL = pas encore assigne a un jeu.
    jeu                 jeu_corpus,
    anonymise           BOOLEAN NOT NULL DEFAULT true, -- §13.3/§13.6 : anonymisation systématique du corpus
    date_annotation     TIMESTAMPTZ,
    importe_par_id      UUID REFERENCES utilisateurs(id) ON DELETE SET NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_documents_corpus_jeu ON documents_corpus(jeu);
CREATE INDEX idx_documents_corpus_type_gabarit ON documents_corpus(type_gabarit);

-- ordre_annotation permet la double annotation avec recoupement (§8.1 étape 3) :
-- deux annotateurs indépendants produisent chacun une ligne (ordre 1 et 2) pour
-- le même (document_id, champ) ; l'écart entre les deux est arbitré en amont
-- de l'entraînement (processus applicatif, hors DB).
CREATE TABLE annotations (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    document_id             UUID NOT NULL REFERENCES documents_corpus(id) ON DELETE CASCADE,
    champ                   VARCHAR(100) NOT NULL, -- ex: 'matricule', 'note', 'code_matiere'
    valeur_verite_terrain   TEXT NOT NULL,
    coordonnees_zone        JSONB NOT NULL, -- bounding box : {x, y, largeur, hauteur}
    annotateur_id           UUID NOT NULL REFERENCES utilisateurs(id) ON DELETE RESTRICT,
    ordre_annotation        SMALLINT NOT NULL DEFAULT 1 CHECK (ordre_annotation IN (1, 2)),
    statut_verification     VARCHAR(30) NOT NULL DEFAULT 'en_attente', -- 'en_attente' | 'concordant' | 'arbitre'
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_annotation_champ_ordre UNIQUE (document_id, champ, ordre_annotation)
);

CREATE INDEX idx_annotations_document ON annotations(document_id);

CREATE TABLE modeles_ocr (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    version                 VARCHAR(50) NOT NULL UNIQUE, -- ex: 'siarn-ocr-v1.3.0'
    chemin_traineddata      TEXT NOT NULL,
    date_entrainement       TIMESTAMPTZ NOT NULL,
    cer                     NUMERIC(5,2) NOT NULL, -- Character Error Rate, jeu de test indépendant (§8.2 : cible < 3%)
    wer                     NUMERIC(5,2) NOT NULL, -- Word Error Rate
    taille_corpus_train     INTEGER NOT NULL,
    taille_corpus_val       INTEGER NOT NULL,
    taille_corpus_test      INTEGER NOT NULL,
    statut                  statut_modele_ocr NOT NULL DEFAULT 'en_entrainement',
    notes                   TEXT, -- observations libres (analyse d'erreurs, matrice de confusion en pièce jointe, etc.)
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_modeles_ocr_statut ON modeles_ocr(statut);

-- Un seul modèle actif à la fois (le microservice d'inférence en charge un seul
-- en production) ; les autres versions restent consultables mais 'archive'.
CREATE UNIQUE INDEX uq_un_seul_modele_actif ON modeles_ocr(statut) WHERE statut = 'actif';

-- FK différée depuis 0004 : chaque PV traité conserve la trace du modèle qui
-- l'a extrait, pour audit/traçabilité (§8.3 : "historique conservé, modèle en
-- production traçable").
ALTER TABLE proces_verbaux
    ADD CONSTRAINT fk_pv_modele_ocr FOREIGN KEY (modele_ocr_id)
    REFERENCES modeles_ocr(id) ON DELETE SET NULL;

CREATE INDEX idx_pv_modele_ocr ON proces_verbaux(modele_ocr_id);
