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


async def send_start_transaction(
    ocpp_identifier: str,
    connector_number: int,
    meter_start_wh: int,
) -> dict:
    return await _post("/api/internal/ocpp/transactions/start", {
        "ocpp_identifier": ocpp_identifier,
        "connector_number": connector_number,
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


async def _post(path: str, payload: dict) -> dict:
    url = f"{LARAVEL_BASE_URL}{path}"

    async with httpx.AsyncClient() as client:
        response = await client.post(url, json=payload, headers=_headers())

    if response.status_code >= 400:
        try:
            message = response.json().get("message", response.text)
        except ValueError:
            message = response.text
        raise BridgeClientError(response.status_code, message)

    return response.json()
