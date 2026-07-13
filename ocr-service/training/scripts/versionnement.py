"""Versionnement et déploiement du modèle — PRD §8.1 étape 9, §8.3.

Enregistre une nouvelle version dans `modeles_ocr` (0006_corpus_ocr.sql) après
évaluation (§8.1 étape 7). Un seul modèle 'actif' à la fois (contrainte unique
partielle en base) : promouvoir un candidat archive automatiquement l'actif
précédent, dans la même transaction.
"""

import psycopg


def enregistrer_modele_candidat(
    connexion: psycopg.Connection,
    version: str,
    chemin_traineddata: str,
    date_entrainement: str,
    cer: float,
    wer: float,
    taille_corpus_train: int,
    taille_corpus_val: int,
    taille_corpus_test: int,
    notes: str | None = None,
) -> str:
    with connexion.cursor() as curseur:
        curseur.execute(
            """
            INSERT INTO modeles_ocr
                (version, chemin_traineddata, date_entrainement, cer, wer,
                 taille_corpus_train, taille_corpus_val, taille_corpus_test, statut, notes)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 'candidat', %s)
            RETURNING id
            """,
            (version, chemin_traineddata, date_entrainement, cer, wer,
             taille_corpus_train, taille_corpus_val, taille_corpus_test, notes),
        )
        (modele_id,) = curseur.fetchone()
    connexion.commit()
    return str(modele_id)


def promouvoir_modele_actif(connexion: psycopg.Connection, modele_id: str, seuil_cer_max: float = 3.0) -> None:
    """Promeut un modèle 'candidat' en 'actif' — seulement si son CER passe le
    seuil cible (§8.2 : CER < 3 % sur les champs numériques)."""
    with connexion.cursor() as curseur:
        curseur.execute("SELECT cer FROM modeles_ocr WHERE id = %s", (modele_id,))
        (cer,) = curseur.fetchone()
        if cer >= seuil_cer_max:
            raise ValueError(
                f"CER {cer}% >= seuil cible {seuil_cer_max}% : promotion refusée (§8.2)"
            )

        curseur.execute("UPDATE modeles_ocr SET statut = 'archive' WHERE statut = 'actif'")
        curseur.execute("UPDATE modeles_ocr SET statut = 'actif' WHERE id = %s", (modele_id,))
    connexion.commit()
