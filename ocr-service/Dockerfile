FROM python:3.12-slim

# tesseract-ocr : moteur de base : Modele_OCR fine-tunés (.traineddata) montés
# séparément en volume (§8.3) et sélectionnés via OCR_MODELE_OCR_VERSION.
RUN apt-get update && apt-get install -y --no-install-recommends \
    tesseract-ocr \
    libgl1 \
    libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY inference/ ./inference/
COPY training/ ./training/

EXPOSE 8000

CMD ["uvicorn", "inference.app.main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]
