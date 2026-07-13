from fastapi import APIRouter

router = APIRouter(tags=["sante"])


@router.get("/sante")
def sante():
    return {"statut": "ok"}
