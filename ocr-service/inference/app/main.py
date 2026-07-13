from fastapi import FastAPI

from .routers import extraction, sante

app = FastAPI(
    title="SIARN — Microservice OCR",
    description="Prétraitement OpenCV + inférence OCR entraînée sur mesure (PRD §7.3, §8)",
    version="0.1.0",
)

app.include_router(sante.router)
app.include_router(extraction.router)
