from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    # Répertoire contenant les .traineddata versionnés (Modele_OCR, §8.3),
    # monté en volume Docker partagé avec le pipeline d'entraînement.
    tessdata_dir: str = "/models/tessdata"
    # Version active à charger au démarrage ; en production ce champ doit
    # correspondre à l'unique Modele_OCR au statut 'actif' (0006_corpus_ocr.sql).
    modele_ocr_version: str = "siarn-ocr-dev"
    database_url: str = "postgresql://siarn:siarn@postgres:5432/siarn"
    # Seuil sous lequel un champ est marqué à vérifier obligatoirement (§7.3, §7.5)
    seuil_confiance_champ: float = 0.85

    class Config:
        env_prefix = "OCR_"


settings = Settings()
