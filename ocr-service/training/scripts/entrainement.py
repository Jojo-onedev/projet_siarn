"""Entraînement (fine-tuning) — PRD §8.1 étape 6.

Fine-tuning du moteur LSTM Tesseract via tesstrain (https://github.com/tesseract-ocr/tesstrain),
à partir d'un modèle pré-entraîné (ex. 'fra' ou 'eng' selon le gabarit).
tesstrain n'est pas vendu dans ce repo : il est attendu comme sous-module/outil
externe invoqué ici en subprocess, avec le corpus annoté (§8.1 étapes 1-5) en entrée.

Prérequis externe à installer sur la machine d'entraînement (hors scope Docker
dev de base, cf. E5) : tesseract-ocr, lstmtraining, et le clone de tesstrain.
"""

import subprocess
from pathlib import Path


def lancer_finetuning(
    repertoire_tesstrain: Path,
    nom_modele: str,
    modele_depart: str,
    repertoire_corpus_train: Path,
    max_iterations: int = 10000,
) -> subprocess.CompletedProcess:
    """Lance `make training` de tesstrain avec les paramètres du corpus SIARN.
    La courbe d'apprentissage et l'early stopping sont gérés par tesstrain
    lui-même (paramètres MAX_ITERATIONS / early stopping via lstmeval)."""
    commande = [
        "make",
        "training",
        f"MODEL_NAME={nom_modele}",
        f"START_MODEL={modele_depart}",
        f"GROUND_TRUTH_DIR={repertoire_corpus_train}",
        f"MAX_ITERATIONS={max_iterations}",
    ]
    return subprocess.run(commande, cwd=repertoire_tesstrain, check=True, capture_output=True, text=True)
