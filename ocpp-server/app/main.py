import logging

from fastapi import FastAPI, WebSocket
from websockets.exceptions import ConnectionClosed

from app import registry
from app.charge_point import BorneOpsChargePoint16
from app.charge_point_v201 import BorneOpsChargePoint201
from app.commands import router as commands_router
from app.ws_adapter import FastApiWebSocketAdapter

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("ocpp-gateway")

app = FastAPI(title="BorneOPS OCPP Gateway")
app.include_router(commands_router)

SUPPORTED_SUBPROTOCOLS = {"ocpp1.6", "ocpp2.0.1"}


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "borneops-ocpp-gateway"}


@app.websocket("/ocpp/{ocpp_identifier}")
async def ocpp_websocket(websocket: WebSocket, ocpp_identifier: str):
    offered_protocols = [
        p.strip() for p in websocket.headers.get("sec-websocket-protocol", "").split(",") if p.strip()
    ]

    supported_offered = [p for p in offered_protocols if p in SUPPORTED_SUBPROTOCOLS]

    if not supported_offered:
        logger.warning(
            f"Rejecting connection for {ocpp_identifier}: no supported OCPP subprotocol offered "
            f"(offered: {offered_protocols or 'none'})."
        )
        await websocket.close(code=1002, reason="Unsupported or missing OCPP subprotocol")
        return

    subprotocol = "ocpp2.0.1" if "ocpp2.0.1" in supported_offered else "ocpp1.6"
    chosen_version = "2.0.1" if subprotocol == "ocpp2.0.1" else "1.6"

    await websocket.accept(subprotocol=subprotocol)
    connection = FastApiWebSocketAdapter(websocket)

    if chosen_version == "2.0.1":
        charge_point = BorneOpsChargePoint201(ocpp_identifier, connection)
    else:
        charge_point = BorneOpsChargePoint16(ocpp_identifier, connection)

    await registry.register(ocpp_identifier, charge_point)
    logger.info(f"Charge point connected: {ocpp_identifier} (OCPP {chosen_version})")

    try:
        await charge_point.start()
    except ConnectionClosed:
        logger.info(f"Charge point disconnected: {ocpp_identifier}")
    finally:
        registry.unregister(ocpp_identifier, charge_point)
