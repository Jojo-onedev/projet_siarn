"""Generateur de corpus SYNTHETIQUE — outil de developpement/smoke-test uniquement.

Ce module ne remplace PAS la collecte de PV reels (§8.1 etape 1, §14, §17 -
risque numero 1 du projet : "corpus d'entrainement insuffisant"). Il sert a
verifier mecaniquement que le pipeline (generation lstmf -> lstmtraining ->
stop_training -> evaluation -> versionnement) fonctionne de bout en bout,
avant qu'un vrai corpus institutionnel ne soit disponible. La qualite du
modele resultant n'a aucune valeur predictive sur des PV reels.
"""

import subprocess
from dataclasses import dataclass
from pathlib import Path

POLICE_PAR_DEFAUT = "DejaVu Sans"

# Echantillons representatifs des champs critiques du PV (§8.2 : notes,
# matricules) - a etoffer/remplacer par de vraies annotations des que le
# corpus institutionnel existe (E4).
TEXTES_PAR_DEFAUT = [
    "12/20", "15.5/20", "08/20", "20/20", "00/20",
    "MAT2026001", "MAT2026042", "MAT2025317",
    "KABORE Awa", "OUEDRAOGO Issa", "SAWADOGO Fatim",
]


@dataclass(frozen=True)
class ExempleCorpus:
    chemin_lstmf: Path   # utilise pour l'entrainement (lstmtraining)
    chemin_image: Path   # utilise pour l'evaluation (inference tesseract normale)
    verite_terrain: str


def generer_exemple(texte: str, prefixe_sortie: Path, police: str = POLICE_PAR_DEFAUT) -> ExempleCorpus:
    prefixe_sortie.parent.mkdir(parents=True, exist_ok=True)
    fichier_texte = prefixe_sortie.with_suffix(".txt")
    fichier_texte.write_text(texte, encoding="utf-8")

    subprocess.run(
        ["text2image", f"--font={police}", f"--text={fichier_texte}",
         f"--outputbase={prefixe_sortie}", "--fonts_dir=/usr/share/fonts"],
        check=True, capture_output=True, text=True,
    )

    prefixe_sortie.with_suffix(".gt.txt").write_text(texte, encoding="utf-8")

    subprocess.run(
        ["tesseract", f"{prefixe_sortie}.tif", str(prefixe_sortie), "--psm", "7", "lstm.train"],
        check=True, capture_output=True, text=True,
    )

    return ExempleCorpus(
        chemin_lstmf=prefixe_sortie.with_suffix(".lstmf"),
        chemin_image=prefixe_sortie.with_suffix(".tif"),
        verite_terrain=texte,
    )


def generer_corpus(textes: list[str], repertoire_sortie: Path) -> list[ExempleCorpus]:
    return [
        generer_exemple(texte, repertoire_sortie / f"exemple_{i:03d}")
        for i, texte in enumerate(textes)
    ]
