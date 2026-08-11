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
from app.meter_values import extract_energy_wh
from app.stop_reason import map_stop_reason

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

    @on("StartTransaction")
    async def on_start_transaction(self, connector_id, id_tag, meter_start, timestamp, **kwargs):
        try:
            result = await bridge_client.send_start_transaction(
                self.id,
                connector_id,
                meter_start,
            )
        except BridgeClientError as e:
            logger.warning(f"StartTransaction bridge call failed for {self.id}: {e}")
            return call_result.StartTransaction(
                transaction_id=0,
                id_tag_info=IdTagInfo(status=AuthorizationStatus.invalid.value),
            )

        transaction_id = int(result["ocpp_transaction_id"])

        return call_result.StartTransaction(
            transaction_id=transaction_id,
            id_tag_info=IdTagInfo(status=AuthorizationStatus.accepted.value),
        )

    @on("MeterValues")
    async def on_meter_values(self, connector_id, meter_value, transaction_id=None, **kwargs):
        if transaction_id is None:
            logger.info(f"MeterValues with no transaction_id on {self.id}, ignoring.")
            return call_result.MeterValues()

        energy_wh = extract_energy_wh(meter_value)

        if energy_wh is None:
            logger.info(f"MeterValues with no usable energy reading on {self.id}, ignoring.")
            return call_result.MeterValues()

        try:
            await bridge_client.send_meter_values(
                self.id,
                str(transaction_id),
                energy_wh,
            )
        except BridgeClientError as e:
            logger.warning(f"MeterValues bridge call failed for {self.id}: {e}")

        return call_result.MeterValues()

    @on("StopTransaction")
    async def on_stop_transaction(self, meter_stop, timestamp, transaction_id, reason=None, **kwargs):
        reason_code = map_stop_reason(reason)

        try:
            await bridge_client.send_stop_transaction(
                self.id,
                str(transaction_id),
                meter_stop,
                reason_code,
            )
        except BridgeClientError as e:
            logger.warning(f"StopTransaction bridge call failed for {self.id}: {e}")

        return call_result.StopTransaction()


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
