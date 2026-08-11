import httpx
import pytest
from unittest.mock import AsyncMock, patch, MagicMock

from app.bridge_client import send_heartbeat, BridgeClientError


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
