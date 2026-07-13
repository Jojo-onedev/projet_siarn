"""Endpoint d'inférence OCR — PRD §7.3 (E3/E6).

Ce microservice ne connaît pas le modèle métier (PV, Note, Étudiant) : il
reçoit une image et un type de gabarit, renvoie une extraction structurée
avec score de confiance par champ. Le backend-api (Laravel) reste seul
responsable de la persistance et de la machine à états (§9), en cohérence
avec l'isolation des microservices (§11.1)."""

import numpy as np
from cv2 import IMREAD_COLOR, imdecode
from fastapi import APIRouter, File, Form, UploadFile

from ..ocr_engine import extraire_champ
from ..preprocessing import pretraiter
from ..segmentation import extraire_zones

router = APIRouter(prefix="/extraction", tags=["extraction"])


@router.post("")
async def extraire_pv(
    fichier: UploadFile = File(...),
    type_gabarit: str = Form("defaut"),
):
    contenu = await fichier.read()
    image = imdecode(np.frombuffer(contenu, np.uint8), IMREAD_COLOR)

    image_pretraitee = pretraiter(image)
    zones = extraire_zones(image_pretraitee, type_gabarit)

    champs = [extraire_champ(image_zone, nom) for nom, image_zone in zones.items()]

    return {
        "type_gabarit": type_gabarit,
        "champs": champs,
    }
