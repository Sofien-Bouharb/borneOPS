import httpx
import pytest
from unittest.mock import AsyncMock, patch, MagicMock

from app.bridge_client import send_start_transaction, BridgeClientError


def _fake_response(status_code: int, json_data: dict):
    response = MagicMock()
    response.status_code = status_code
    response.json.return_value = json_data
    response.text = str(json_data)
    return response


@pytest.mark.asyncio
async def test_retries_on_5xx_and_eventually_succeeds():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(side_effect=[
        _fake_response(503, {"message": "Service unavailable"}),
        _fake_response(200, {"message": "Transaction started.", "session_id": 1, "ocpp_transaction_id": "1"}),
    ])

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client
        with patch("asyncio.sleep", new=AsyncMock()):
            result = await send_start_transaction("CP-001", 1, 5000)

    assert result["session_id"] == 1
    assert fake_client.post.await_count == 2


@pytest.mark.asyncio
async def test_does_not_retry_on_4xx():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(return_value=_fake_response(409, {"message": "Connector occupied"}))

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client
        with patch("asyncio.sleep", new=AsyncMock()):
            with pytest.raises(BridgeClientError) as exc_info:
                await send_start_transaction("CP-001", 1, 5000)

    assert exc_info.value.status_code == 409
    assert fake_client.post.await_count == 1


@pytest.mark.asyncio
async def test_raises_after_exhausting_all_retries():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(side_effect=httpx.ConnectError("Connection refused"))

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client
        with patch("asyncio.sleep", new=AsyncMock()):
            with pytest.raises(BridgeClientError) as exc_info:
                await send_start_transaction("CP-001", 1, 5000)

    assert exc_info.value.status_code == 503
    assert fake_client.post.await_count == 3
