"""Prétraitement d'image du PV scanné — PRD §7.3 : redressement (deskew),
débruitage, binarisation adaptative, avant segmentation et OCR."""

import cv2
import numpy as np


def redresser(image: np.ndarray) -> np.ndarray:
    """Deskew : corrige l'inclinaison du scan via la boîte englobante minimale
    des pixels non blancs."""
    gris = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    _, seuil = cv2.threshold(gris, 0, 255, cv2.THRESH_BINARY_INV | cv2.THRESH_OTSU)
    coords = np.column_stack(np.where(seuil > 0))
    if coords.size == 0:
        return image
    angle = cv2.minAreaRect(coords)[-1]
    angle = -(90 + angle) if angle < -45 else -angle
    (h, w) = image.shape[:2]
    centre = (w // 2, h // 2)
    matrice = cv2.getRotationMatrix2D(centre, angle, 1.0)
    return cv2.warpAffine(
        image, matrice, (w, h), flags=cv2.INTER_CUBIC, borderMode=cv2.BORDER_REPLICATE
    )


def debruiter(image: np.ndarray) -> np.ndarray:
    """Débruitage non local (adapté aux scans avec grain/poussière)."""
    return cv2.fastNlMeansDenoisingColored(image, None, 10, 10, 7, 21)


def binariser(image: np.ndarray) -> np.ndarray:
    """Binarisation adaptative (gère les PV avec éclairage de scan inégal)."""
    gris = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    return cv2.adaptiveThreshold(
        gris, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 31, 15
    )


def pretraiter(image: np.ndarray) -> np.ndarray:
    """Pipeline complet §7.3 : deskew -> débruitage -> binarisation."""
    image = redresser(image)
    image = debruiter(image)
    return binariser(image)
