import pytest
from unittest.mock import AsyncMock, patch

from app.charge_point import BorneOpsChargePoint16, _map_ocpp_status
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
async def test_boot_notification_calls_bridge_and_returns_accepted(charge_point):
    with patch("app.charge_point.bridge_client.send_boot_notification", new=AsyncMock()) as mock_call:
        result = await charge_point.on_boot_notification(
            charge_point_vendor="AcmeCharge",
            charge_point_model="ModelZ",
        )

    mock_call.assert_awaited_once_with("TEST-CP-001")
    assert result.status == "Accepted"
    assert result.interval == 60
    assert result.current_time is not None


@pytest.mark.asyncio
async def test_boot_notification_still_accepts_when_bridge_call_fails(charge_point):
    with patch(
        "app.charge_point.bridge_client.send_boot_notification",
        new=AsyncMock(side_effect=BridgeClientError(404, "No station found")),
    ):
        result = await charge_point.on_boot_notification(
            charge_point_vendor="AcmeCharge",
            charge_point_model="ModelZ",
        )

    assert result.status == "Accepted"


@pytest.mark.asyncio
async def test_heartbeat_calls_bridge_and_returns_current_time(charge_point):
    with patch("app.charge_point.bridge_client.send_heartbeat", new=AsyncMock()) as mock_call:
        result = await charge_point.on_heartbeat()

    mock_call.assert_awaited_once_with("TEST-CP-001")
    assert result.current_time is not None


@pytest.mark.asyncio
async def test_heartbeat_still_acknowledges_when_bridge_call_fails(charge_point):
    with patch(
        "app.charge_point.bridge_client.send_heartbeat",
        new=AsyncMock(side_effect=BridgeClientError(404, "No station found")),
    ):
        result = await charge_point.on_heartbeat()

    assert result.current_time is not None


@pytest.mark.asyncio
async def test_status_notification_station_level_passes_no_connector_number(charge_point):
    with patch("app.charge_point.bridge_client.send_status_notification", new=AsyncMock()) as mock_call:
        await charge_point.on_status_notification(
            connector_id=0,
            status="Available",
            error_code="NoError",
        )

    mock_call.assert_awaited_once_with("TEST-CP-001", "available", None)


@pytest.mark.asyncio
async def test_status_notification_connector_level_passes_connector_number(charge_point):
    with patch("app.charge_point.bridge_client.send_status_notification", new=AsyncMock()) as mock_call:
        await charge_point.on_status_notification(
            connector_id=1,
            status="Charging",
            error_code="NoError",
        )

    mock_call.assert_awaited_once_with("TEST-CP-001", "occupied", 1)


@pytest.mark.parametrize("ocpp_status,expected", [
    ("Available", "available"),
    ("Preparing", "occupied"),
    ("Charging", "occupied"),
    ("SuspendedEVSE", "occupied"),
    ("SuspendedEV", "occupied"),
    ("Finishing", "occupied"),
    ("Reserved", "occupied"),
    ("Unavailable", "out_of_service"),
    ("Faulted", "fault"),
    ("SomeUnknownStatus", "fault"),
])
def test_map_ocpp_status(ocpp_status, expected):
    assert _map_ocpp_status(ocpp_status) == expected
