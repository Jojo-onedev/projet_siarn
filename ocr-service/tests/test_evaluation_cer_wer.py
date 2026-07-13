from training.scripts.evaluation_cer_wer import calculer_cer, calculer_wer, evaluer_jeu_test


def test_cer_identique():
    assert calculer_cer("12/20", "12/20") == 0.0


def test_cer_une_erreur_caractere():
    assert calculer_cer("12/20", "1Z/20") == 1 / 5


def test_wer_identique():
    assert calculer_wer("NGUEMA Jean", "NGUEMA Jean") == 0.0


def test_wer_un_mot_faux():
    assert calculer_wer("NGUEMA Jean", "NGUEMA Jeans") == 1 / 2


def test_evaluer_jeu_test_moyenne():
    resultat = evaluer_jeu_test([("12/20", "12/20"), ("15/20", "1S/20")])
    assert resultat["n_exemples"] == 2
    assert resultat["cer_moyen"] > 0.0
