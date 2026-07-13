"""Split train/val/test du corpus — PRD §8.1 étape 4.

Répartition ~70/15/15, SANS chevauchement de documents entre jeux : le split
se fait au niveau du document entier (pas de la ligne d'annotation), pour
qu'aucun champ d'un même PV ne fuite d'un jeu à l'autre.
"""

import random


def repartir_documents(
    ids_documents: list[str],
    ratio_train: float = 0.70,
    ratio_val: float = 0.15,
    graine: int = 42,
) -> dict[str, list[str]]:
    documents = list(ids_documents)
    random.Random(graine).shuffle(documents)

    n = len(documents)
    n_train = round(n * ratio_train)
    n_val = round(n * ratio_val)

    return {
        "train": documents[:n_train],
        "val": documents[n_train : n_train + n_val],
        "test": documents[n_train + n_val :],
    }
