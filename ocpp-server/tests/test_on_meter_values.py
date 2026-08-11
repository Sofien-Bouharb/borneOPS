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


VALID_METER_VALUE = [
    {
        "timestamp": "2026-08-11T15:00:00Z",
        "sampled_value": [
            {"value": "5500", "measurand": "Energy.Active.Import.Register", "unit": "Wh"},
        ],
    }
]


@pytest.mark.asyncio
async def test_meter_values_calls_bridge_with_extracted_wh(charge_point):
    with patch("app.charge_point.bridge_client.send_meter_values", new=AsyncMock()) as mock_call:
        result = await charge_point.on_meter_values(
            connector_id=1,
            meter_value=VALID_METER_VALUE,
            transaction_id=42,
        )

    mock_call.assert_awaited_once_with("TEST-CP-001", "42", 5500)
    assert result is not None


@pytest.mark.asyncio
async def test_meter_values_skips_bridge_call_when_transaction_id_missing(charge_point):
    with patch("app.charge_point.bridge_client.send_meter_values", new=AsyncMock()) as mock_call:
        await charge_point.on_meter_values(
            connector_id=1,
            meter_value=VALID_METER_VALUE,
            transaction_id=None,
        )

    mock_call.assert_not_awaited()


@pytest.mark.asyncio
async def test_meter_values_skips_bridge_call_when_reading_unparseable(charge_point):
    unparseable = [{"timestamp": "2026-08-11T15:00:00Z", "sampled_value": []}]

    with patch("app.charge_point.bridge_client.send_meter_values", new=AsyncMock()) as mock_call:
        await charge_point.on_meter_values(
            connector_id=1,
            meter_value=unparseable,
            transaction_id=42,
        )

    mock_call.assert_not_awaited()


@pytest.mark.asyncio
async def test_meter_values_still_acknowledges_when_bridge_call_fails(charge_point):
    with patch(
        "app.charge_point.bridge_client.send_meter_values",
        new=AsyncMock(side_effect=BridgeClientError(409, "Le relevé du compteur ne peut pas diminuer.")),
    ):
        result = await charge_point.on_meter_values(
            connector_id=1,
            meter_value=VALID_METER_VALUE,
            transaction_id=42,
        )

    assert result is not None
