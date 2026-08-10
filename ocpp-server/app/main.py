from fastapi import FastAPI

app = FastAPI(title="BorneOPS OCPP Gateway")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "borneops-ocpp-gateway"}
