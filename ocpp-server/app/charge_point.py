import logging
from datetime import datetime, timezone

from ocpp.routing import on
from ocpp.v16 import ChargePoint as ChargePoint16
from ocpp.v16 import call_result
from ocpp.v16.datatypes import IdTagInfo
from ocpp.v16.enums import AuthorizationStatus, RegistrationStatus

from app import bridge_client
from app.authorization import TestAuthorizationProvider
from app.bridge_client import BridgeClientError

logger = logging.getLogger("ocpp-gateway")


def _now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


class BorneOpsChargePoint16(ChargePoint16):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.authorization_provider = TestAuthorizationProvider()

    @on("BootNotification")
    async def on_boot_notification(self, charge_point_vendor, charge_point_model, **kwargs):
        try:
            await bridge_client.send_boot_notification(self.id)
        except BridgeClientError as e:
            logger.warning(f"BootNotification bridge call failed for {self.id}: {e}")

        return call_result.BootNotification(
            current_time=_now_iso(),
            interval=60,
            status=RegistrationStatus.accepted.value,
        )

    @on("Heartbeat")
    async def on_heartbeat(self):
        try:
            await bridge_client.send_heartbeat(self.id)
        except BridgeClientError as e:
            logger.warning(f"Heartbeat bridge call failed for {self.id}: {e}")

        return call_result.Heartbeat(current_time=_now_iso())

    @on("StatusNotification")
    async def on_status_notification(self, connector_id, status, error_code, **kwargs):
        operational_status = _map_ocpp_status(status)
        connector_number = connector_id if connector_id and connector_id > 0 else None

        try:
            await bridge_client.send_status_notification(
                self.id,
                operational_status,
                connector_number,
            )
        except BridgeClientError as e:
            logger.warning(f"StatusNotification bridge call failed for {self.id}: {e}")

        return call_result.StatusNotification()

    @on("Authorize")
    async def on_authorize(self, id_tag, **kwargs):
        if self.authorization_provider.is_authorized(id_tag):
            status = AuthorizationStatus.accepted.value
        else:
            status = AuthorizationStatus.invalid.value
            logger.info(f"Authorization rejected for idTag '{id_tag}' on {self.id}")

        return call_result.Authorize(id_tag_info=IdTagInfo(status=status))


def _map_ocpp_status(ocpp_status: str) -> str:
    mapping = {
        "Available": "available",
        "Preparing": "occupied",
        "Charging": "occupied",
        "SuspendedEVSE": "occupied",
        "SuspendedEV": "occupied",
        "Finishing": "occupied",
        "Reserved": "occupied",
        "Unavailable": "out_of_service",
        "Faulted": "fault",
    }
    return mapping.get(ocpp_status, "fault")
