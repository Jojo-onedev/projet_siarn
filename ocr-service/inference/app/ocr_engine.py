"""Application du modèle OCR entraîné — PRD §7.3, §8.

Tant qu'aucun modèle fine-tuné (Modele_OCR statut='actif', §8) n'est
disponible, ce module utilise le modèle Tesseract de base comme repli de
développement — cela n'est PAS le moteur cible : voir ocr-service/training/
pour le pipeline de fine-tuning qui produit le .traineddata versionné destiné
à remplacer ce repli avant toute mise en production (§8.1, seuil §8.2 : CER < 3 %).
"""

import os

import numpy as np
import pytesseract

from .config import settings


def _config_tesseract() -> str:
    os.environ["TESSDATA_PREFIX"] = settings.tessdata_dir
    return f"--oem 1 --psm 6 -l {settings.modele_ocr_version}"


def extraire_champ(image_zone: np.ndarray, nom_champ: str) -> dict:
    """Retourne le texte extrait et un score de confiance [0,1] pour un champ
    (matricule, note, code_matière...) — pré-remplissage + score par champ (§7.3)."""
    try:
        config = _config_tesseract()
        data = pytesseract.image_to_data(
            image_zone, config=config, output_type=pytesseract.Output.DICT
        )
    except pytesseract.TesseractError:
        # Modèle custom absent (dev sans .traineddata) : repli sur l'anglais/latin par défaut
        data = pytesseract.image_to_data(
            image_zone, config="--oem 1 --psm 6", output_type=pytesseract.Output.DICT
        )

    mots = [m for m in data["text"] if m.strip()]
    confiances = [int(c) for c, m in zip(data["conf"], data["text"]) if m.strip() and c != "-1"]

    texte = " ".join(mots).strip()
    confiance_moyenne = (sum(confiances) / len(confiances) / 100) if confiances else 0.0

    return {
        "champ": nom_champ,
        "valeur": texte,
        "score_confiance": round(confiance_moyenne, 4),
        "verification_requise": confiance_moyenne < settings.seuil_confiance_champ,
    }
