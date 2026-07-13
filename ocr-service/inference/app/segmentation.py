"""Détection/segmentation des zones d'intérêt du PV — PRD §7.3.

Les PV suivent des gabarits documentaires fixes propres à l'établissement
(type_gabarit, cf. Document_Corpus en base). Une approche par zones
relatives configurées par gabarit est donc pertinente ici (pas de détection
d'objets par ML) : chaque gabarit déclare ses zones one fois, calibrées
lors de la constitution du corpus (E4), puis réutilisées pour tout PV du
même gabarit.

Cette registry est un point de départ ; elle doit être enrichie/calibrée à
partir des gabarits réels collectés en E4 (§8.1 étape 1).
"""

from dataclasses import dataclass

import numpy as np


@dataclass(frozen=True)
class ZoneRelative:
    nom: str  # 'en_tete' | 'tableau_notes' | 'matricules' | 'signatures'
    x: float  # coordonnées relatives [0,1] au sein de l'image normalisée
    y: float
    largeur: float
    hauteur: float


GABARITS: dict[str, list[ZoneRelative]] = {
    # Gabarit par défaut, à remplacer par les gabarits réels de l'établissement
    "defaut": [
        ZoneRelative("en_tete", 0.0, 0.0, 1.0, 0.15),
        ZoneRelative("tableau_notes", 0.0, 0.15, 1.0, 0.75),
        ZoneRelative("signatures", 0.0, 0.90, 1.0, 0.10),
    ],
}


def zones_pour_gabarit(type_gabarit: str) -> list[ZoneRelative]:
    return GABARITS.get(type_gabarit, GABARITS["defaut"])


def extraire_zones(image: np.ndarray, type_gabarit: str) -> dict[str, np.ndarray]:
    hauteur_image, largeur_image = image.shape[:2]
    zones = {}
    for zone in zones_pour_gabarit(type_gabarit):
        x0 = int(zone.x * largeur_image)
        y0 = int(zone.y * hauteur_image)
        x1 = int((zone.x + zone.largeur) * largeur_image)
        y1 = int((zone.y + zone.hauteur) * hauteur_image)
        zones[zone.nom] = image[y0:y1, x0:x1]
    return zones


def zones_en_pixels(image: np.ndarray, type_gabarit: str) -> list[dict]:
    """Coordonnees pixel des zones (pour surlignage cote frontend, §7.5),
    sans recouper l'image (utilise par l'endpoint de pretraitement seul,
    E3 - la decoupe reelle par zone reste dans extraire_zones pour l'OCR, E6)."""
    hauteur_image, largeur_image = image.shape[:2]
    return [
        {
            "nom": zone.nom,
            "x": int(zone.x * largeur_image),
            "y": int(zone.y * hauteur_image),
            "largeur": int(zone.largeur * largeur_image),
            "hauteur": int(zone.hauteur * hauteur_image),
        }
        for zone in zones_pour_gabarit(type_gabarit)
    ]
