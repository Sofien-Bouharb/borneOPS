import logging

from fastapi import APIRouter, Header, HTTPException
from ocpp.v16 import call as call16
from pydantic import BaseModel

from app import registry
from app.config import OCPP_BRIDGE_TOKEN

logger = logging.getLogger("ocpp-gateway")

router = APIRouter(prefix="/commands", tags=["commands"])


def _require_bridge_token(x_ocpp_bridge_token: str = Header(default="")) -> None:
    if not OCPP_BRIDGE_TOKEN or x_ocpp_bridge_token != OCPP_BRIDGE_TOKEN:
        raise HTTPException(status_code=401, detail="A valid OCPP bridge token is required.")


def _get_connected_charge_point(ocpp_identifier: str):
    charge_point = registry.get(ocpp_identifier)
    if charge_point is None:
        raise HTTPException(status_code=404, detail=f"'{ocpp_identifier}' is not currently connected.")
    return charge_point


class RemoteStartBody(BaseModel):
    id_tag: str
    connector_number: int | None = None


class RemoteStopBody(BaseModel):
    ocpp_transaction_id: str


class ResetBody(BaseModel):
    type: str = "Soft"


class UnlockConnectorBody(BaseModel):
    connector_number: int


@router.post("/{ocpp_identifier}/remote-start")
async def remote_start(ocpp_identifier: str, body: RemoteStartBody, x_ocpp_bridge_token: str = Header(default="")):
    _require_bridge_token(x_ocpp_bridge_token)
    charge_point = _get_connected_charge_point(ocpp_identifier)

    response = await charge_point.call(call16.RemoteStartTransaction(
        id_tag=body.id_tag,
        connector_id=body.connector_number,
    ))

    return {"status": response.status}


@router.post("/{ocpp_identifier}/remote-stop")
async def remote_stop(ocpp_identifier: str, body: RemoteStopBody, x_ocpp_bridge_token: str = Header(default="")):
    _require_bridge_token(x_ocpp_bridge_token)
    charge_point = _get_connected_charge_point(ocpp_identifier)

    try:
        transaction_id = int(body.ocpp_transaction_id)
    except ValueError:
        raise HTTPException(status_code=422, detail="ocpp_transaction_id must be numeric for OCPP 1.6 RemoteStopTransaction.")

    response = await charge_point.call(call16.RemoteStopTransaction(transaction_id=transaction_id))

    return {"status": response.status}


@router.post("/{ocpp_identifier}/reset")
async def reset(ocpp_identifier: str, body: ResetBody, x_ocpp_bridge_token: str = Header(default="")):
    _require_bridge_token(x_ocpp_bridge_token)
    charge_point = _get_connected_charge_point(ocpp_identifier)

    if body.type not in ("Hard", "Soft"):
        raise HTTPException(status_code=422, detail="type must be 'Hard' or 'Soft'.")

    response = await charge_point.call(call16.Reset(type=body.type))

    return {"status": response.status}


@router.post("/{ocpp_identifier}/unlock-connector")
async def unlock_connector(ocpp_identifier: str, body: UnlockConnectorBody, x_ocpp_bridge_token: str = Header(default="")):
    _require_bridge_token(x_ocpp_bridge_token)
    charge_point = _get_connected_charge_point(ocpp_identifier)

    response = await charge_point.call(call16.UnlockConnector(connector_id=body.connector_number))

    return {"status": response.status}
