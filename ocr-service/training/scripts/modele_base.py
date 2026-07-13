"""Preparation du modele de base pour le fine-tuning LSTM — PRD §8.1 etape 6.

Le paquet tesseract-ocr-{lang} (apt, Debian/Ubuntu) fournit une version
"fast" (entiere quantifiee) du traineddata, qui NE PEUT PAS servir de point
de depart a un `lstmtraining --continue_from` (verifie experimentalement :
lstmtraining refuse avec "is an integer (fast) model, cannot continue
training"). Le modele de base fine-tunable ("best", flottant) est telecharge
depuis tessdata_best (memes gabarits que le projet officiel Tesseract),
puis desassemble avec `combine_tessdata -u` pour en extraire le checkpoint
`.lstm` utilisable comme point de depart.
"""

import subprocess
import urllib.request
from pathlib import Path

URL_TESSDATA_BEST = "https://github.com/tesseract-ocr/tessdata_best/raw/main/{lang}.traineddata"


def telecharger_traineddata_best(langue: str, repertoire_cache: Path) -> Path:
    repertoire_cache.mkdir(parents=True, exist_ok=True)
    chemin = repertoire_cache / f"{langue}.traineddata"

    if not chemin.exists():
        urllib.request.urlretrieve(URL_TESSDATA_BEST.format(lang=langue), chemin)

    return chemin


def preparer_checkpoint_base(langue: str, repertoire_cache: Path) -> Path:
    """Retourne le chemin du prefixe <repertoire_cache>/<langue>_base
    (fichiers <prefixe>.lstm, .lstm-unicharset, etc. desassembles)."""
    traineddata = telecharger_traineddata_best(langue, repertoire_cache)
    prefixe = repertoire_cache / f"{langue}_base"

    if not Path(f"{prefixe}.lstm").exists():
        subprocess.run(
            ["combine_tessdata", "-u", str(traineddata), str(prefixe)],
            check=True, capture_output=True, text=True,
        )

    return prefixe
