"""Pretraitement + segmentation seuls — PRD §7.3 (E3).

Distinct de /extraction (§7.3/§8, E6) : E3 ne fait PAS d'OCR. Tant qu'aucun
modele Modele_OCR entraine (E5) n'est actif, le PV reste en statut
'en_traitement' cote backend-api - on ne simule jamais une extraction de
champs sans le pipeline d'entrainement qui la justifie (regle non
negociable du projet).
"""

import base64

import numpy as np
from cv2 import IMREAD_COLOR, imdecode, imencode
from fastapi import APIRouter, File, Form, HTTPException, UploadFile

from ..preprocessing import pretraiter
from ..segmentation import zones_en_pixels

router = APIRouter(prefix="/pretraitement", tags=["pretraitement"])


@router.post("")
async def pretraiter_pv(
    fichier: UploadFile = File(...),
    type_gabarit: str = Form("defaut"),
):
    contenu = await fichier.read()
    image = imdecode(np.frombuffer(contenu, np.uint8), IMREAD_COLOR)

    if image is None:
        raise HTTPException(status_code=422, detail="image_illisible")

    image_pretraitee = pretraiter(image)
    zones = zones_en_pixels(image_pretraitee, type_gabarit)

    ok, tampon = imencode(".png", image_pretraitee)
    image_base64 = base64.b64encode(tampon.tobytes()).decode("ascii") if ok else None

    return {
        "type_gabarit": type_gabarit,
        "zones": zones,
        "image_pretraitee_base64": image_base64,
    }
