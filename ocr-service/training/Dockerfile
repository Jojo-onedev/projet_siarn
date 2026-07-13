# Image dediee a l'entrainement OCR (§8, E5) — separee de inference/Dockerfile :
# les outils de fine-tuning (lstmtraining, text2image, polices) sont inutiles
# et alourdiraient l'image d'inference qui tourne en continu (§11.1, isolation
# des microservices pour la montee en charge).
FROM python:3.12-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    tesseract-ocr \
    tesseract-ocr-fra \
    fonts-dejavu-core \
    libgl1 \
    libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY training/ ./training/

ENTRYPOINT ["python", "-m", "training.pipeline"]
# --repertoire-travail doit pointer vers un volume monte pour que le modele
# de base tessdata_best (~4 Mo, telecharge une fois) persiste entre executions
# (§11.1 : ressources serveurs limitees) - voir docker-compose.yml.
CMD ["--demo", "--repertoire-travail=/travail"]
