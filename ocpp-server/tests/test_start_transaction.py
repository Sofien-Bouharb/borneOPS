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
async def test_start_transaction_calls_bridge_and_returns_assigned_transaction_id(charge_point):
    with patch(
        "app.charge_point.bridge_client.send_start_transaction",
        new=AsyncMock(return_value={"message": "Transaction started.", "session_id": 42, "ocpp_transaction_id": "42"}),
    ) as mock_call:
        result = await charge_point.on_start_transaction(
            connector_id=1,
            id_tag="TAG-DRIVER-001",
            meter_start=4000,
            timestamp="2026-08-11T15:00:00Z",
        )

    mock_call.assert_awaited_once_with("TEST-CP-001", 1, 4000, "TAG-DRIVER-001")
    assert result.transaction_id == 42
    assert result.id_tag_info.status == "Accepted"


@pytest.mark.asyncio
async def test_start_transaction_rejects_when_bridge_call_fails(charge_point):
    with patch(
        "app.charge_point.bridge_client.send_start_transaction",
        new=AsyncMock(side_effect=BridgeClientError(409, "Connector already occupied")),
    ):
        result = await charge_point.on_start_transaction(
            connector_id=1,
            id_tag="TAG-DRIVER-001",
            meter_start=4000,
            timestamp="2026-08-11T15:00:00Z",
        )

    assert result.transaction_id == 0
    assert result.id_tag_info.status == "Invalid"


@pytest.mark.asyncio
async def test_start_transaction_rejects_when_station_unknown(charge_point):
    with patch(
        "app.charge_point.bridge_client.send_start_transaction",
        new=AsyncMock(side_effect=BridgeClientError(404, "No station found")),
    ):
        result = await charge_point.on_start_transaction(
            connector_id=1,
            id_tag="TAG-DRIVER-001",
            meter_start=4000,
            timestamp="2026-08-11T15:00:00Z",
        )

    assert result.transaction_id == 0
    assert result.id_tag_info.status == "Invalid"
