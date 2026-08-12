import asyncio
import httpx

from app.config import LARAVEL_BASE_URL, OCPP_BRIDGE_TOKEN


class BridgeClientError(Exception):
    def __init__(self, status_code: int, message: str):
        self.status_code = status_code
        self.message = message
        super().__init__(f"Bridge call failed ({status_code}): {message}")


def _headers() -> dict[str, str]:
    return {
        "X-OCPP-Bridge-Token": OCPP_BRIDGE_TOKEN,
        "Content-Type": "application/json",
    }


async def send_heartbeat(ocpp_identifier: str) -> dict:
    return await _post("/api/internal/ocpp/events/heartbeat", {
        "ocpp_identifier": ocpp_identifier,
    })


async def send_boot_notification(ocpp_identifier: str) -> dict:
    return await _post("/api/internal/ocpp/events/boot-notification", {
        "ocpp_identifier": ocpp_identifier,
    })


async def send_status_notification(
    ocpp_identifier: str,
    operational_status: str,
    connector_number: int | None = None,
) -> dict:
    payload = {
        "ocpp_identifier": ocpp_identifier,
        "operational_status": operational_status,
    }
    if connector_number is not None:
        payload["connector_number"] = connector_number

    return await _post("/api/internal/ocpp/events/status-notification", payload)


async def verify_station_credential(ocpp_identifier: str, password: str) -> bool:
    result = await _post("/api/internal/ocpp/verify-station-credential", {
        "ocpp_identifier": ocpp_identifier,
        "password": password,
    })
    return result.get("authorized", False)



async def send_start_transaction(
    ocpp_identifier: str,
    connector_number: int,
    meter_start_wh: int,
) -> dict:
    return await _post_with_retry("/api/internal/ocpp/transactions/start", {
        "ocpp_identifier": ocpp_identifier,
        "connector_number": connector_number,
        "meter_start_wh": meter_start_wh,
    })


async def send_start_transaction_external(
    ocpp_identifier: str,
    connector_number: int,
    external_transaction_id: str,
    meter_start_wh: int,
) -> dict:
    return await _post_with_retry("/api/internal/ocpp/transactions/start-external", {
        "ocpp_identifier": ocpp_identifier,
        "connector_number": connector_number,
        "external_transaction_id": external_transaction_id,
        "meter_start_wh": meter_start_wh,
    })


async def send_meter_values(
    ocpp_identifier: str,
    ocpp_transaction_id: str,
    meter_value_wh: int,
) -> dict:
    return await _post("/api/internal/ocpp/transactions/meter-values", {
        "ocpp_identifier": ocpp_identifier,
        "ocpp_transaction_id": ocpp_transaction_id,
        "meter_value_wh": meter_value_wh,
    })


async def send_stop_transaction(
    ocpp_identifier: str,
    ocpp_transaction_id: str,
    meter_stop_wh: int,
    reason_code: str,
) -> dict:
    return await _post_with_retry("/api/internal/ocpp/transactions/stop", {
        "ocpp_identifier": ocpp_identifier,
        "ocpp_transaction_id": ocpp_transaction_id,
        "meter_stop_wh": meter_stop_wh,
        "reason_code": reason_code,
    })


async def _post(path: str, payload: dict) -> dict:
    url = f"{LARAVEL_BASE_URL}{path}"

    try:
        async with httpx.AsyncClient(timeout=10.0) as client:
            response = await client.post(url, json=payload, headers=_headers())
    except httpx.RequestError as e:
        raise BridgeClientError(503, f"Could not reach Laravel bridge: {e}") from e

    if response.status_code >= 400:
        try:
            message = response.json().get("message", response.text)
        except ValueError:
            message = response.text
        raise BridgeClientError(response.status_code, message)

    return response.json()


async def _post_with_retry(path: str, payload: dict, attempts: int = 3, backoff_seconds: float = 0.5) -> dict:
    """
    Used only for transaction-state-changing calls (StartTransaction,
    TransactionEvent Started, StopTransaction / TransactionEvent Ended).

    A single transient network blip must not be treated the same as a
    genuine, durable failure to persist a transaction — retrying a bounded
    number of times before giving up avoids incorrectly rejecting a
    legitimate StartTransaction, and gives StopTransaction a real chance to
    land before falling back to durable reconciliation logging (see
    charge_point.py / charge_point_v201.py for what happens after all
    attempts are exhausted).

    Only retries on connection-level failures and 5xx responses — a 4xx
    (validation error, 404 station/connector, 409 conflict) is a genuine,
    stable rejection that retrying will not fix, so it is raised
    immediately.
    """
    last_error: BridgeClientError | None = None

    for attempt in range(1, attempts + 1):
        try:
            return await _post(path, payload)
        except BridgeClientError as e:
            if e.status_code < 500:
                raise
            last_error = e
            if attempt < attempts:
                await asyncio.sleep(backoff_seconds * attempt)

    raise last_error
