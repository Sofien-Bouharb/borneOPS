import logging
import secrets

from fastapi import APIRouter, Header, HTTPException
from ocpp.v16 import call as call16
from ocpp.v201 import call as call201
from ocpp.v201.datatypes import IdTokenType
from ocpp.v201.enums import IdTokenEnumType, ResetEnumType
from pydantic import BaseModel

from app import registry
from app.charge_point_v201 import BorneOpsChargePoint201
from app.config import OCPP_BRIDGE_TOKEN

logger = logging.getLogger("ocpp-gateway")

router = APIRouter(prefix="/commands", tags=["commands"])

# OCPP 1.6 uses "Hard"/"Soft" for Reset.type; OCPP 2.0.1 uses
# "Immediate"/"OnIdle" for the same concepts. Laravel always sends the 1.6
# vocabulary; this maps it to the correct 2.0.1 enum value when the live
# connection is negotiated as 2.0.1.
_RESET_TYPE_1_6_TO_2_0_1 = {
    "Hard": "Immediate",
    "Soft": "OnIdle",
}


def _require_bridge_token(x_ocpp_bridge_token: str = Header(default="")) -> None:
    if not OCPP_BRIDGE_TOKEN or x_ocpp_bridge_token != OCPP_BRIDGE_TOKEN:
        raise HTTPException(status_code=401, detail="A valid OCPP bridge token is required.")


def _get_connected_charge_point(ocpp_identifier: str):
    charge_point = registry.get(ocpp_identifier)
    if charge_point is None:
        raise HTTPException(status_code=404, detail=f"'{ocpp_identifier}' is not currently connected.")
    return charge_point


def _is_v201(charge_point) -> bool:
    # The registry stores the live ChargePoint instance itself (see
    # app/registry.py) — whichever class actually negotiated the
    # connection. That instance's class is the single source of truth for
    # which protocol is live; there is no separate stored protocol tag to
    # drift out of sync with it.
    return isinstance(charge_point, BorneOpsChargePoint201)


def _generate_remote_start_id() -> int:
    # OCPP 2.0.1's RequestStartTransaction requires a CSMS-assigned
    # correlation id (remote_start_id). This gateway does not currently
    # correlate the resulting TransactionEvent(Started) back to this id —
    # that correlation instead happens via the EVSE/connector resolution
    # built for the 2.0.1 session-start path — so a random value is
    # sufficient here; it only needs to be present and syntactically valid.
    return secrets.randbelow(2_147_483_647) + 1


class RemoteStartBody(BaseModel):
    id_tag: str
    connector_number: int | None = None


class RemoteStopBody(BaseModel):
    ocpp_transaction_id: str


class ResetBody(BaseModel):
    type: str = "Soft"


class UnlockConnectorBody(BaseModel):
    connector_number: int
    evse_id: int | None = None
    connector_id: int | None = None


@router.post("/{ocpp_identifier}/remote-start")
async def remote_start(ocpp_identifier: str, body: RemoteStartBody, x_ocpp_bridge_token: str = Header(default="")):
    _require_bridge_token(x_ocpp_bridge_token)
    charge_point = _get_connected_charge_point(ocpp_identifier)

    if _is_v201(charge_point):
        response = await charge_point.call(call201.RequestStartTransaction(
            id_token=IdTokenType(id_token=body.id_tag, type=IdTokenEnumType.central),
            remote_start_id=_generate_remote_start_id(),
            evse_id=body.connector_number,
        ))
    else:
        response = await charge_point.call(call16.RemoteStartTransaction(
            id_tag=body.id_tag,
            connector_id=body.connector_number,
        ))

    return {"status": response.status}


@router.post("/{ocpp_identifier}/remote-stop")
async def remote_stop(ocpp_identifier: str, body: RemoteStopBody, x_ocpp_bridge_token: str = Header(default="")):
    _require_bridge_token(x_ocpp_bridge_token)
    charge_point = _get_connected_charge_point(ocpp_identifier)

    if _is_v201(charge_point):
        response = await charge_point.call(call201.RequestStopTransaction(
            transaction_id=body.ocpp_transaction_id,
        ))
    else:
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

    if _is_v201(charge_point):
        mapped_type = ResetEnumType(_RESET_TYPE_1_6_TO_2_0_1[body.type])
        response = await charge_point.call(call201.Reset(type=mapped_type))
    else:
        response = await charge_point.call(call16.Reset(type=body.type))

    return {"status": response.status}


@router.post("/{ocpp_identifier}/unlock-connector")
async def unlock_connector(ocpp_identifier: str, body: UnlockConnectorBody, x_ocpp_bridge_token: str = Header(default="")):
    _require_bridge_token(x_ocpp_bridge_token)
    charge_point = _get_connected_charge_point(ocpp_identifier)

    if _is_v201(charge_point):
        if body.evse_id is None or body.connector_id is None:
            raise HTTPException(
                status_code=422,
                detail="evse_id and connector_id are both required to unlock a connector on an OCPP 2.0.1 station.",
            )

        response = await charge_point.call(call201.UnlockConnector(
            evse_id=body.evse_id,
            connector_id=body.connector_id,
        ))
    else:
        response = await charge_point.call(call16.UnlockConnector(connector_id=body.connector_number))

    return {"status": response.status}
