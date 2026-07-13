"""Evaluation d'un modele entraine sur un jeu de test — PRD §8.1 etape 7.

Fait tourner l'inference Tesseract normale (pas lstmtraining) avec le
.traineddata nouvellement produit, puis delegue le calcul CER/WER a
evaluation_cer_wer.py.
"""

import subprocess
from pathlib import Path

from .corpus_synthetique import ExempleCorpus
from .evaluation_cer_wer import evaluer_jeu_test


def inferer(chemin_image: Path, chemin_traineddata: Path) -> str:
    resultat = subprocess.run(
        [
            "tesseract", str(chemin_image), "stdout",
            "--tessdata-dir", str(chemin_traineddata.parent),
            "-l", chemin_traineddata.stem,
            "--psm", "7",
        ],
        check=True, capture_output=True, text=True,
    )
    return resultat.stdout.strip()


def evaluer_modele(exemples_test: list[ExempleCorpus], chemin_traineddata: Path) -> dict:
    paires = [(ex.verite_terrain, inferer(ex.chemin_image, chemin_traineddata)) for ex in exemples_test]
    return evaluer_jeu_test(paires)
