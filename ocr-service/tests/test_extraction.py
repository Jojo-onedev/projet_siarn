import numpy as np
from cv2 import imencode
from fastapi.testclient import TestClient

from inference.app.main import app

client = TestClient(app)


def _image_png(largeur: int, hauteur: int) -> bytes:
    image = np.full((hauteur, largeur, 3), 255, dtype=np.uint8)
    ok, tampon = imencode(".png", image)
    assert ok
    return tampon.tobytes()


def test_extraction_retourne_des_champs():
    reponse = client.post(
        "/extraction",
        files={"fichier": ("test.png", _image_png(400, 200), "image/png")},
        data={"type_gabarit": "defaut"},
    )

    assert reponse.status_code == 200
    corps = reponse.json()
    assert {c["champ"] for c in corps["champs"]} == {"en_tete", "tableau_notes", "signatures"}
    for champ in corps["champs"]:
        assert "score_confiance" in champ
        assert "verification_requise" in champ


def test_extraction_image_degeneree_ne_plante_pas():
    """Regression : une image 1x1 (zones de taille nulle une fois segmentees)
    faisait planter pytesseract avec une 500 ("cannot write empty image")
    au lieu de renvoyer un champ non exploitable."""
    reponse = client.post(
        "/extraction",
        files={"fichier": ("test.png", _image_png(1, 1), "image/png")},
        data={"type_gabarit": "defaut"},
    )

    assert reponse.status_code == 200
    for champ in reponse.json()["champs"]:
        assert champ["valeur"] == ""
        assert champ["score_confiance"] == 0.0
        assert champ["verification_requise"] is True
