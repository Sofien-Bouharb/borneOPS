import logging
from datetime import datetime, timezone

from ocpp.routing import on
from ocpp.v201 import ChargePoint as ChargePoint201
from ocpp.v201 import call_result
from ocpp.v201.datatypes import IdTokenInfoType
from ocpp.v201.enums import AuthorizationStatusEnumType, RegistrationStatusEnumType

from app import bridge_client
from app.authorization import build_authorization_provider
from app.bridge_client import BridgeClientError
from app.meter_values import extract_energy_wh_v201
from app.stop_reason import map_stop_reason

from app.reconciliation import log_unreconciled_stop

logger = logging.getLogger("ocpp-gateway")


def _now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


class BorneOpsChargePoint201(ChargePoint201):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.authorization_provider = build_authorization_provider()

    @on("BootNotification")
    async def on_boot_notification(self, charging_station, reason, **kwargs):
        try:
            await bridge_client.send_boot_notification(self.id)
        except BridgeClientError as e:
            logger.warning(f"BootNotification bridge call failed for {self.id}: {e}")

        return call_result.BootNotification(
            current_time=_now_iso(),
            interval=60,
            status=RegistrationStatusEnumType.accepted.value,
        )

    @on("Heartbeat")
    async def on_heartbeat(self):
        try:
            await bridge_client.send_heartbeat(self.id)
        except BridgeClientError as e:
            logger.warning(f"Heartbeat bridge call failed for {self.id}: {e}")

        return call_result.Heartbeat(current_time=_now_iso())

    @on("StatusNotification")
    async def on_status_notification(self, timestamp, connector_status, evse_id, connector_id, **kwargs):
        operational_status = _map_ocpp201_status(connector_status)

        try:
            await bridge_client.send_status_notification(
                self.id,
                operational_status,
                connector_id,
            )
        except BridgeClientError as e:
            logger.warning(f"StatusNotification bridge call failed for {self.id}: {e}")

        return call_result.StatusNotification()

    @on("Authorize")
    async def on_authorize(self, id_token, **kwargs):
        tag_value = id_token.get("id_token") if isinstance(id_token, dict) else id_token

        if await self.authorization_provider.is_authorized(tag_value, self.id):
            status = AuthorizationStatusEnumType.accepted.value
        else:
            status = AuthorizationStatusEnumType.invalid.value
            logger.info(f"Authorization rejected for idToken '{tag_value}' on {self.id}")

        return call_result.Authorize(id_token_info=IdTokenInfoType(status=status))

    @on("TransactionEvent")
    async def on_transaction_event(
        self,
        event_type,
        timestamp,
        trigger_reason,
        seq_no,
        transaction_info,
        meter_value=None,
        evse=None,
        id_token=None,
        **kwargs,
    ):
        transaction_id = transaction_info.get("transaction_id")

        # This gateway has no database access and therefore no way to
        # legitimately resolve which BorneOPS connector an (evse_id,
        # connector_id) pair refers to. That resolution — including
        # rejecting genuinely ambiguous cases rather than guessing — is
        # Laravel's job, using the real ocpp_evse_id/ocpp_connector_id
        # columns. This handler only relays what the charger actually sent.
        # connector_id is optional per the OCPP 2.0.1 spec when an EVSE has
        # exactly one connector; it is passed through as None when absent
        # rather than being defaulted to anything here.
        evse_id = evse.get("id") if evse else None
        connector_id = evse.get("connector_id") if evse else None

        energy_wh = extract_energy_wh_v201(meter_value) if meter_value else None

        if event_type == "Started":
            try:
                await bridge_client.send_start_transaction_external(
                    self.id,
                    evse_id,
                    connector_id,
                    transaction_id,
                    energy_wh if energy_wh is not None else 0,
                )
            except BridgeClientError as e:
                logger.warning(f"TransactionEvent(Started) bridge call failed for {self.id}: {e}")

        elif event_type == "Updated":
            if energy_wh is not None:
                try:
                    await bridge_client.send_meter_values(self.id, transaction_id, energy_wh)
                except BridgeClientError as e:
                    logger.warning(f"TransactionEvent(Updated) bridge call failed for {self.id}: {e}")

        elif event_type == "Ended":
            stopped_reason = transaction_info.get("stopped_reason")
            reason_code = map_stop_reason(stopped_reason)
            final_meter_wh = energy_wh if energy_wh is not None else 0
            try:
                await bridge_client.send_stop_transaction(
                    self.id,
                    transaction_id,
                    final_meter_wh,
                    reason_code,
                )
            except BridgeClientError as e:
                # Same protocol limitation as OCPP 1.6: TransactionEvent's
                # response carries no field to signal rejection to the
                # charger for an Ended event. send_stop_transaction() has
                # already retried transient/5xx failures internally.
                log_unreconciled_stop(self.id, transaction_id, final_meter_wh, reason_code, str(e))

        return call_result.TransactionEvent()


def _map_ocpp201_status(connector_status: str) -> str:
    mapping = {
        "Available": "available",
        "Occupied": "occupied",
        "Reserved": "occupied",
        "Unavailable": "out_of_service",
        "Faulted": "fault",
    }
    return mapping.get(connector_status, "fault")
