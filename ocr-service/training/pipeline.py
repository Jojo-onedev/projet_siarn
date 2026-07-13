#!/usr/bin/env python3
"""Orchestration du pipeline d'entrainement OCR — PRD §8.1 (etapes 6-9).

Enchaine : preparation du modele de base -> generation/chargement du corpus
-> fine-tuning LSTM -> evaluation CER/WER -> versionnement (modeles_ocr).

Usage (smoke-test, corpus synthetique — voir corpus_synthetique.py pour les
limites de cette approche) :
    python -m training.pipeline --demo

La version "production" (corpus reel via documents_corpus/annotations) sera
branchee ici une fois qu'un corpus institutionnel existera (§17 risque n°1) :
le corpus reel remplace simplement generer_corpus_demo() par une requete sur
documents_corpus (E4), le reste du pipeline est deja pret a l'usage.
"""

import argparse
import os
from datetime import datetime, timezone
from pathlib import Path

import psycopg

from training.scripts.corpus_synthetique import TEXTES_PAR_DEFAUT, generer_corpus
from training.scripts.entrainement import (
    ecrire_liste_fichiers,
    finaliser_traineddata,
    lancer_finetuning,
)
from training.scripts.evaluation_modele import evaluer_modele
from training.scripts.modele_base import preparer_checkpoint_base, telecharger_traineddata_best
from training.scripts.versionnement import enregistrer_modele_candidat

LANGUE = "fra"


def generer_corpus_demo(repertoire: Path, ratio_test: float = 0.25):
    """Corpus SYNTHETIQUE (smoke-test uniquement, cf. corpus_synthetique.py)."""
    exemples = generer_corpus(TEXTES_PAR_DEFAUT, repertoire)
    n_test = max(1, int(len(exemples) * ratio_test))
    return exemples[n_test:], exemples[:n_test]  # train, test


def executer(repertoire_travail: Path, repertoire_modeles: Path, max_iterations: int, version: str) -> dict:
    repertoire_travail.mkdir(parents=True, exist_ok=True)
    repertoire_modeles.mkdir(parents=True, exist_ok=True)
    base = repertoire_travail / "base"
    corpus = repertoire_travail / "corpus"
    sortie = repertoire_travail / "sortie"

    print(f"[1/5] Preparation du modele de base ({LANGUE}, tessdata_best)...")
    traineddata_reference = telecharger_traineddata_best(LANGUE, base)
    prefixe_base = preparer_checkpoint_base(LANGUE, base)

    print("[2/5] Generation du corpus (demo synthetique)...")
    exemples_train, exemples_test = generer_corpus_demo(corpus)
    liste_train = ecrire_liste_fichiers([e.chemin_lstmf for e in exemples_train], repertoire_travail / "train_listfile.txt")
    print(f"      {len(exemples_train)} exemples train, {len(exemples_test)} exemples test")

    print(f"[3/5] Fine-tuning LSTM ({max_iterations} iterations)...")
    prefixe_checkpoint = lancer_finetuning(prefixe_base, traineddata_reference, liste_train, sortie, max_iterations)

    print("[4/5] Finalisation du .traineddata et evaluation CER/WER...")
    # Ecrit directement dans le volume partage avec l'inference (§8.3, E6) :
    # promouvoir ce modele en 'actif' n'exige alors qu'un changement de statut
    # en base, le fichier est deja au bon endroit pour que tesseract -l
    # <version> le trouve (TESSDATA_PREFIX=/models/tessdata cote inference).
    chemin_traineddata = finaliser_traineddata(prefixe_checkpoint, traineddata_reference, repertoire_modeles / f"{version}.traineddata")
    resultat_evaluation = evaluer_modele(exemples_test, chemin_traineddata)
    print(f"      CER moyen = {resultat_evaluation['cer_moyen'] * 100:.2f} %, WER moyen = {resultat_evaluation['wer_moyen'] * 100:.2f} %")

    print("[5/5] Versionnement (modeles_ocr)...")
    with psycopg.connect(os.environ["OCR_DATABASE_URL"]) as connexion:
        modele_id = enregistrer_modele_candidat(
            connexion,
            version=version,
            chemin_traineddata=str(chemin_traineddata),
            date_entrainement=datetime.now(timezone.utc).isoformat(),
            cer=round(resultat_evaluation["cer_moyen"] * 100, 2),
            wer=round(resultat_evaluation["wer_moyen"] * 100, 2),
            taille_corpus_train=len(exemples_train),
            taille_corpus_val=0,
            taille_corpus_test=len(exemples_test),
            notes="Corpus synthetique (smoke-test pipeline) - non representatif de PV reels, cf. §17.",
        )
    print(f"      Modele enregistre : id={modele_id}, statut=candidat")

    return {"modele_id": modele_id, **resultat_evaluation, "chemin_traineddata": str(chemin_traineddata)}


if __name__ == "__main__":
    analyseur = argparse.ArgumentParser(description=__doc__)
    analyseur.add_argument("--demo", action="store_true", help="Lance le smoke-test avec corpus synthetique")
    analyseur.add_argument("--iterations", type=int, default=100)
    analyseur.add_argument("--version", default=f"siarn-ocr-demo-{datetime.now().strftime('%Y%m%d%H%M%S')}")
    analyseur.add_argument("--repertoire-travail", default="/tmp/siarn-training")
    analyseur.add_argument("--repertoire-modeles", default="/models/tessdata")
    args = analyseur.parse_args()

    if not args.demo:
        raise SystemExit("Seul --demo (corpus synthetique) est disponible tant qu'aucun corpus reel n'existe (§17).")

    resultat = executer(Path(args.repertoire_travail), Path(args.repertoire_modeles), args.iterations, args.version)
    print(resultat)
