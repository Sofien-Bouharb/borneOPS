import logging

from fastapi import FastAPI, WebSocket
from websockets.exceptions import ConnectionClosed

from app.charge_point import BorneOpsChargePoint16
from app.charge_point_v201 import BorneOpsChargePoint201
from app.ws_adapter import FastApiWebSocketAdapter

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("ocpp-gateway")

app = FastAPI(title="BorneOPS OCPP Gateway")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "borneops-ocpp-gateway"}


@app.websocket("/ocpp/{ocpp_identifier}")
async def ocpp_websocket(websocket: WebSocket, ocpp_identifier: str):
    offered_protocols = [
        p.strip() for p in websocket.headers.get("sec-websocket-protocol", "").split(",") if p.strip()
    ]

    if "ocpp2.0.1" in offered_protocols:
        subprotocol = "ocpp2.0.1"
        chosen_version = "2.0.1"
    else:
        subprotocol = "ocpp1.6"
        chosen_version = "1.6"

    await websocket.accept(subprotocol=subprotocol)

    connection = FastApiWebSocketAdapter(websocket)

    if chosen_version == "2.0.1":
        charge_point = BorneOpsChargePoint201(ocpp_identifier, connection)
    else:
        charge_point = BorneOpsChargePoint16(ocpp_identifier, connection)

    logger.info(f"Charge point connected: {ocpp_identifier} (OCPP {chosen_version})")

    try:
        await charge_point.start()
    except ConnectionClosed:
        logger.info(f"Charge point disconnected: {ocpp_identifier}")
