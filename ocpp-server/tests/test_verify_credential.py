import pytest
from unittest.mock import AsyncMock, patch, MagicMock

from app.bridge_client import verify_station_credential


def _fake_response(status_code: int, json_data: dict):
    response = MagicMock()
    response.status_code = status_code
    response.json.return_value = json_data
    response.text = str(json_data)
    return response


@pytest.mark.asyncio
async def test_returns_true_when_authorized():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(return_value=_fake_response(200, {"authorized": True}))

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client
        result = await verify_station_credential("CP-001", "correct-password", "1.6")

    assert result is True


@pytest.mark.asyncio
async def test_returns_false_when_not_authorized():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(return_value=_fake_response(
        200, {"authorized": False, "reason": "invalid_credential"}
    ))

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client
        result = await verify_station_credential("CP-001", "wrong-password", "1.6")

    assert result is False


@pytest.mark.asyncio
async def test_sends_negotiated_version_in_payload():
    fake_client = AsyncMock()
    fake_client.post = AsyncMock(return_value=_fake_response(200, {"authorized": True}))

    with patch("httpx.AsyncClient") as mock_client_cls:
        mock_client_cls.return_value.__aenter__.return_value = fake_client
        await verify_station_credential("CP201-001", "correct-password", "2.0.1")

    sent_payload = fake_client.post.call_args.kwargs["json"]
    assert sent_payload == {
        "ocpp_identifier": "CP201-001",
        "password": "correct-password",
        "negotiated_version": "2.0.1",
    }
