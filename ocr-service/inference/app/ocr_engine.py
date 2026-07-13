"""Application du modèle OCR entraîné — PRD §7.3, §8.

Tant qu'aucun modèle fine-tuné (Modele_OCR statut='actif', §8) n'est
disponible, ce module utilise le modèle Tesseract de base comme repli de
développement — cela n'est PAS le moteur cible : voir ocr-service/training/
pour le pipeline de fine-tuning qui produit le .traineddata versionné destiné
à remplacer ce repli avant toute mise en production (§8.1, seuil §8.2 : CER < 3 %).
"""

import numpy as np
import pytesseract

from .config import settings

# Repli dev : tessdata "systeme" du paquet tesseract-ocr (contient eng/osd),
# distinct de settings.tessdata_dir (uniquement nos modeles personnalises,
# §8.3). Passe explicitement en --tessdata-dir par tentative plutot que via
# la variable d'environnement globale TESSDATA_PREFIX, pour que le repli
# fonctionne meme quand le modele custom est introuvable (les deux tentatives
# ne doivent pas se marcher dessus).
TESSDATA_SYSTEME = "/usr/share/tesseract-ocr/5/tessdata"


def _config_tesseract(modele_ocr_version: str) -> str:
    return f"--oem 1 --psm 6 -l {modele_ocr_version} --tessdata-dir {settings.tessdata_dir}"


def extraire_champ(image_zone: np.ndarray, nom_champ: str, modele_ocr_version: str | None = None) -> dict:
    """Retourne le texte extrait et un score de confiance [0,1] pour un champ
    (matricule, note, code_matière...) — pré-remplissage + score par champ (§7.3).

    modele_ocr_version : version du Modele_OCR a utiliser (§8.3), transmise
    par backend-api (seul proprietaire de la table modeles_ocr, §11.1 - ce
    microservice reste stateless et ne consulte jamais Postgres lui-meme).
    Repli sur la config par defaut si non fournie (dev sans modele actif)."""
    if image_zone.size == 0 or 0 in image_zone.shape[:2]:
        # Zone degeneree (gabarit mal calibre, scan atypique...) : pytesseract
        # plante sur une image vide ("cannot write empty image") - on renvoie
        # un champ non exploitable plutot qu'une erreur HTTP 500.
        return {
            "champ": nom_champ,
            "valeur": "",
            "score_confiance": 0.0,
            "verification_requise": True,
        }

    try:
        config = _config_tesseract(modele_ocr_version or settings.modele_ocr_version)
        data = pytesseract.image_to_data(
            image_zone, config=config, output_type=pytesseract.Output.DICT
        )
    except pytesseract.TesseractError:
        # Modèle custom absent (dev sans .traineddata actif) : repli sur
        # l'anglais standard du paquet tesseract-ocr, jamais le moteur cible.
        data = pytesseract.image_to_data(
            image_zone,
            config=f"--oem 1 --psm 6 --tessdata-dir {TESSDATA_SYSTEME}",
            output_type=pytesseract.Output.DICT,
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
