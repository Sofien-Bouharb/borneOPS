import httpx
import pytest
from unittest.mock import AsyncMock, patch, MagicMock

from app.bridge_client import send_heartbeat, send_start_transaction_external, BridgeClientError


def _fake_response(status_code: int, json_data: dict):
    response = MagicMock()
    response.status_code = status_code
    response.json.return_value = json_data
    response.text = str(json_data)
    return response


@pytest.mark.asyncio
async def test_send_heartbeat_succeeds_on_200():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(return_value=_fake_response(200, {"message": "Heartbeat recorded."}))

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client
        result = await send_heartbeat("CP-001")

    assert result == {"message": "Heartbeat recorded."}


@pytest.mark.asyncio
async def test_send_heartbeat_raises_bridge_client_error_on_http_error():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(return_value=_fake_response(404, {"message": "No station found."}))

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client

        with pytest.raises(BridgeClientError) as exc_info:
            await send_heartbeat("CP-DOES-NOT-EXIST")

    assert exc_info.value.status_code == 404
    assert "No station found" in exc_info.value.message


@pytest.mark.asyncio
async def test_send_heartbeat_raises_bridge_client_error_when_laravel_unreachable():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(side_effect=httpx.ConnectError("Connection refused"))

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client

        with pytest.raises(BridgeClientError) as exc_info:
            await send_heartbeat("CP-001")

    assert exc_info.value.status_code == 503


@pytest.mark.asyncio
async def test_send_start_transaction_external_includes_connector_id_when_provided():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(return_value=_fake_response(200, {
        "message": "Transaction started.",
        "session_id": 1,
        "ocpp_transaction_id": "CHARGER-TXN-1",
    }))

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client
        await send_start_transaction_external("CP-201", 1, 1, "CHARGER-TXN-1", 1000)

    sent_payload = fake_client.post.call_args.kwargs["json"]
    assert sent_payload == {
        "ocpp_identifier": "CP-201",
        "evse_id": 1,
        "connector_id": 1,
        "external_transaction_id": "CHARGER-TXN-1",
        "meter_start_wh": 1000,
    }


@pytest.mark.asyncio
async def test_send_start_transaction_external_omits_connector_id_when_none():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(return_value=_fake_response(200, {
        "message": "Transaction started.",
        "session_id": 1,
        "ocpp_transaction_id": "CHARGER-TXN-2",
    }))

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client
        await send_start_transaction_external("CP-201", 5, None, "CHARGER-TXN-2", 500)

    sent_payload = fake_client.post.call_args.kwargs["json"]
    assert sent_payload == {
        "ocpp_identifier": "CP-201",
        "evse_id": 5,
        "external_transaction_id": "CHARGER-TXN-2",
        "meter_start_wh": 500,
    }
    assert "connector_id" not in sent_payload
