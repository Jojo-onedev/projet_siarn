"""Entrainement (fine-tuning) — PRD §8.1 etape 6.

Fine-tuning du moteur LSTM Tesseract a partir d'un modele pre-entraine
("best"/flottant, cf. modele_base.py), via les outils `lstmtraining` /
`combine_tessdata` fournis par le paquet tesseract-ocr (verifie : Debian/
Ubuntu les incluent nativement, pas besoin de compiler tesseract ni de
cloner le depot tesstrain separement).
"""

import subprocess
from pathlib import Path


def ecrire_liste_fichiers(chemins_lstmf: list[Path], chemin_liste: Path) -> Path:
    chemin_liste.write_text("\n".join(str(c) for c in chemins_lstmf), encoding="utf-8")
    return chemin_liste


def lancer_finetuning(
    prefixe_modele_base: Path,
    traineddata_reference: Path,
    liste_fichiers_train: Path,
    repertoire_sortie: Path,
    max_iterations: int = 400,
) -> Path:
    """Lance lstmtraining --continue_from. Retourne le prefixe du checkpoint
    produit (<repertoire_sortie>/checkpoint_checkpoint)."""
    repertoire_sortie.mkdir(parents=True, exist_ok=True)
    prefixe_checkpoint = repertoire_sortie / "checkpoint"

    subprocess.run(
        [
            "lstmtraining",
            "--continue_from", f"{prefixe_modele_base}.lstm",
            "--old_traineddata", str(traineddata_reference),
            "--traineddata", str(traineddata_reference),
            "--train_listfile", str(liste_fichiers_train),
            "--model_output", str(prefixe_checkpoint),
            "--max_iterations", str(max_iterations),
        ],
        check=True, capture_output=True, text=True,
    )

    return repertoire_sortie / "checkpoint_checkpoint"


def finaliser_traineddata(
    prefixe_checkpoint: Path,
    traineddata_reference: Path,
    chemin_sortie: Path,
) -> Path:
    """lstmtraining --stop_training : convertit le dernier checkpoint en un
    .traineddata utilisable en inference (§8.1 etape 9)."""
    chemin_sortie.parent.mkdir(parents=True, exist_ok=True)

    subprocess.run(
        [
            "lstmtraining",
            "--stop_training",
            "--continue_from", str(prefixe_checkpoint),
            "--old_traineddata", str(traineddata_reference),
            "--traineddata", str(traineddata_reference),
            "--model_output", str(chemin_sortie),
        ],
        check=True, capture_output=True, text=True,
    )

    return chemin_sortie
