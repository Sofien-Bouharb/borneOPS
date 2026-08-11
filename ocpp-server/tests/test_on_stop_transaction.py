import pytest
from unittest.mock import AsyncMock, patch

from app.charge_point import BorneOpsChargePoint16
from app.bridge_client import BridgeClientError


class FakeConnection:
    async def recv(self):
        raise NotImplementedError

    async def send(self, message):
        pass


@pytest.fixture
def charge_point():
    return BorneOpsChargePoint16("TEST-CP-001", FakeConnection())


@pytest.mark.asyncio
async def test_stop_transaction_calls_bridge_with_mapped_reason(charge_point):
    with patch("app.charge_point.bridge_client.send_stop_transaction", new=AsyncMock()) as mock_call:
        result = await charge_point.on_stop_transaction(
            meter_stop=3500,
            timestamp="2026-08-11T15:00:00Z",
            transaction_id=25,
            reason="EVDisconnected",
        )

    mock_call.assert_awaited_once_with("TEST-CP-001", "25", 3500, "vehicle_disconnected")
    assert result is not None


@pytest.mark.asyncio
async def test_stop_transaction_defaults_to_other_when_reason_missing(charge_point):
    with patch("app.charge_point.bridge_client.send_stop_transaction", new=AsyncMock()) as mock_call:
        await charge_point.on_stop_transaction(
            meter_stop=3500,
            timestamp="2026-08-11T15:00:00Z",
            transaction_id=25,
        )

    mock_call.assert_awaited_once_with("TEST-CP-001", "25", 3500, "other")


@pytest.mark.asyncio
async def test_stop_transaction_still_acknowledges_when_bridge_call_fails(charge_point):
    with patch(
        "app.charge_point.bridge_client.send_stop_transaction",
        new=AsyncMock(side_effect=BridgeClientError(409, "Conflict")),
    ):
        result = await charge_point.on_stop_transaction(
            meter_stop=3500,
            timestamp="2026-08-11T15:00:00Z",
            transaction_id=25,
            reason="Remote",
        )

    assert result is not None


@pytest.mark.asyncio
async def test_stop_transaction_still_acknowledges_for_orphan_transaction(charge_point):
    with patch("app.charge_point.bridge_client.send_stop_transaction", new=AsyncMock(return_value={
        "message": "No matching session found for this transaction. Acknowledged, no action taken.",
    })):
        result = await charge_point.on_stop_transaction(
            meter_stop=5000,
            timestamp="2026-08-11T15:00:00Z",
            transaction_id=999999,
            reason="Other",
        )

    assert result is not None
