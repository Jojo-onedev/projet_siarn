"""Augmentation de données — PRD §8.1 étape 5 : rotation, bruit,
contraste/luminosité, flou. Appliquée uniquement sur le jeu d'entraînement
(jamais sur val/test, qui doivent rester représentatifs des conditions réelles)."""

import cv2
import numpy as np


def appliquer_rotation(image: np.ndarray, angle_max: float = 3.0, graine: int | None = None) -> np.ndarray:
    rng = np.random.default_rng(graine)
    angle = rng.uniform(-angle_max, angle_max)
    h, w = image.shape[:2]
    matrice = cv2.getRotationMatrix2D((w // 2, h // 2), angle, 1.0)
    return cv2.warpAffine(image, matrice, (w, h), borderMode=cv2.BORDER_REPLICATE)


def appliquer_bruit(image: np.ndarray, intensite: float = 8.0, graine: int | None = None) -> np.ndarray:
    rng = np.random.default_rng(graine)
    bruit = rng.normal(0, intensite, image.shape)
    return np.clip(image.astype(np.float32) + bruit, 0, 255).astype(np.uint8)


def ajuster_contraste_luminosite(
    image: np.ndarray, contraste: float = 1.0, luminosite: float = 0.0
) -> np.ndarray:
    return cv2.convertScaleAbs(image, alpha=contraste, beta=luminosite)


def appliquer_flou(image: np.ndarray, noyau: int = 3) -> np.ndarray:
    return cv2.GaussianBlur(image, (noyau, noyau), 0)


def augmenter(image: np.ndarray, graine: int | None = None) -> np.ndarray:
    """Chaîne d'augmentation par défaut appliquée à un exemple d'entraînement."""
    rng = np.random.default_rng(graine)
    image = appliquer_rotation(image, graine=graine)
    image = ajuster_contraste_luminosite(
        image, contraste=float(rng.uniform(0.85, 1.15)), luminosite=float(rng.uniform(-15, 15))
    )
    if rng.random() < 0.5:
        image = appliquer_bruit(image, graine=graine)
    if rng.random() < 0.3:
        image = appliquer_flou(image)
    return image
