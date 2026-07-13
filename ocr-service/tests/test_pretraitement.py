import numpy as np
from cv2 import imencode
from fastapi.testclient import TestClient

from inference.app.main import app

client = TestClient(app)


def _image_test_png() -> bytes:
    image = np.full((200, 400, 3), 255, dtype=np.uint8)
    ok, tampon = imencode(".png", image)
    assert ok
    return tampon.tobytes()


def test_pretraitement_retourne_zones_et_image():
    reponse = client.post(
        "/pretraitement",
        files={"fichier": ("test.png", _image_test_png(), "image/png")},
        data={"type_gabarit": "defaut"},
    )

    assert reponse.status_code == 200
    corps = reponse.json()
    assert corps["type_gabarit"] == "defaut"
    assert {z["nom"] for z in corps["zones"]} == {"en_tete", "tableau_notes", "signatures"}
    assert corps["image_pretraitee_base64"]


def test_pretraitement_image_illisible():
    reponse = client.post(
        "/pretraitement",
        files={"fichier": ("test.png", b"pas une image", "image/png")},
        data={"type_gabarit": "defaut"},
    )

    assert reponse.status_code == 422
