"""Évaluation quantitative — PRD §8.1 étape 7 : CER, WER, précision par champ
critique (matricule, note), sur jeu de test indépendant. Seuil cible §8.2 :
CER < 3 % sur les champs numériques avant toute mise en production."""


def _distance_levenshtein(a: list, b: list) -> int:
    m, n = len(a), len(b)
    dp = list(range(n + 1))
    for i in range(1, m + 1):
        precedent, dp[0] = dp[0], i
        for j in range(1, n + 1):
            temp = dp[j]
            cout = 0 if a[i - 1] == b[j - 1] else 1
            dp[j] = min(dp[j] + 1, dp[j - 1] + 1, precedent + cout)
            precedent = temp
    return dp[n]


def calculer_cer(reference: str, hypothese: str) -> float:
    """Character Error Rate = distance d'édition (caractères) / longueur de référence."""
    if len(reference) == 0:
        return 0.0 if len(hypothese) == 0 else 1.0
    return _distance_levenshtein(list(reference), list(hypothese)) / len(reference)


def calculer_wer(reference: str, hypothese: str) -> float:
    """Word Error Rate = distance d'édition (mots) / nombre de mots de référence."""
    mots_ref = reference.split()
    mots_hyp = hypothese.split()
    if len(mots_ref) == 0:
        return 0.0 if len(mots_hyp) == 0 else 1.0
    return _distance_levenshtein(mots_ref, mots_hyp) / len(mots_ref)


def evaluer_jeu_test(paires_reference_hypothese: list[tuple[str, str]]) -> dict:
    """Agrège CER/WER moyens sur un jeu de test (référence, hypothèse) par champ."""
    if not paires_reference_hypothese:
        return {"cer_moyen": 0.0, "wer_moyen": 0.0, "n_exemples": 0}

    cers = [calculer_cer(ref, hyp) for ref, hyp in paires_reference_hypothese]
    wers = [calculer_wer(ref, hyp) for ref, hyp in paires_reference_hypothese]

    return {
        "cer_moyen": round(sum(cers) / len(cers), 4),
        "wer_moyen": round(sum(wers) / len(wers), 4),
        "n_exemples": len(paires_reference_hypothese),
    }
