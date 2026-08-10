import logging

from fastapi import FastAPI, WebSocket
from websockets.exceptions import ConnectionClosed

from app.charge_point import BorneOpsChargePoint16
from app.ws_adapter import FastApiWebSocketAdapter

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("ocpp-gateway")

app = FastAPI(title="BorneOPS OCPP Gateway")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "borneops-ocpp-gateway"}


@app.websocket("/ocpp/{ocpp_identifier}")
async def ocpp_websocket(websocket: WebSocket, ocpp_identifier: str):
    await websocket.accept(subprotocol="ocpp1.6")

    connection = FastApiWebSocketAdapter(websocket)
    charge_point = BorneOpsChargePoint16(ocpp_identifier, connection)

    logger.info(f"Charge point connected: {ocpp_identifier}")

    try:
        await charge_point.start()
    except ConnectionClosed:
        logger.info(f"Charge point disconnected: {ocpp_identifier}")
