import pytest
from unittest.mock import AsyncMock, patch

from app.charge_point_v201 import BorneOpsChargePoint201
from app.bridge_client import BridgeClientError


class FakeConnection:
    async def recv(self):
        raise NotImplementedError

    async def send(self, message):
        pass


@pytest.fixture
def charge_point():
    return BorneOpsChargePoint201("TEST-CP-201", FakeConnection())


@pytest.mark.asyncio
async def test_boot_notification_calls_bridge_and_returns_accepted(charge_point):
    with patch("app.charge_point_v201.bridge_client.send_boot_notification", new=AsyncMock()) as mock_call:
        result = await charge_point.on_boot_notification(
            charging_station={"model": "ModelZ", "vendor_name": "AcmeCharge"},
            reason="PowerUp",
        )

    mock_call.assert_awaited_once_with("TEST-CP-201")
    assert result.status == "Accepted"


@pytest.mark.asyncio
async def test_heartbeat_calls_bridge_and_returns_current_time(charge_point):
    with patch("app.charge_point_v201.bridge_client.send_heartbeat", new=AsyncMock()) as mock_call:
        result = await charge_point.on_heartbeat()

    mock_call.assert_awaited_once_with("TEST-CP-201")
    assert result.current_time is not None


@pytest.mark.asyncio
async def test_status_notification_always_carries_a_connector_number(charge_point):
    with patch("app.charge_point_v201.bridge_client.send_status_notification", new=AsyncMock()) as mock_call:
        await charge_point.on_status_notification(
            timestamp="2026-08-11T16:41:00Z",
            connector_status="Occupied",
            evse_id=1,
            connector_id=1,
        )

    mock_call.assert_awaited_once_with("TEST-CP-201", "occupied", 1)


@pytest.mark.asyncio
async def test_authorize_accepts_valid_token(charge_point):
    charge_point.authorization_provider.is_authorized = lambda tag: True

    result = await charge_point.on_authorize(id_token={"id_token": "TAG-DRIVER-001", "type": "ISO14443"})

    assert result.id_token_info.status == "Accepted"


@pytest.mark.asyncio
async def test_authorize_rejects_invalid_token(charge_point):
    charge_point.authorization_provider.is_authorized = lambda tag: False

    result = await charge_point.on_authorize(id_token={"id_token": "TAG-UNKNOWN", "type": "ISO14443"})

    assert result.id_token_info.status == "Invalid"


@pytest.mark.asyncio
async def test_transaction_event_started_calls_bridge_with_external_id(charge_point):
    with patch("app.charge_point_v201.bridge_client.send_start_transaction_external", new=AsyncMock()) as mock_call:
        await charge_point.on_transaction_event(
            event_type="Started",
            timestamp="2026-08-11T16:41:10Z",
            trigger_reason="Authorized",
            seq_no=0,
            transaction_info={"transaction_id": "CP201-TXN-001"},
            evse={"id": 1, "connector_id": 1},
            meter_value=[{
                "timestamp": "2026-08-11T16:41:10Z",
                "sampled_value": [{"value": 1000.0, "measurand": "Energy.Active.Import.Register"}],
            }],
        )

    mock_call.assert_awaited_once_with("TEST-CP-201", 1, "CP201-TXN-001", 1000)


@pytest.mark.asyncio
async def test_transaction_event_started_defaults_to_zero_when_no_meter_value(charge_point):
    with patch("app.charge_point_v201.bridge_client.send_start_transaction_external", new=AsyncMock()) as mock_call:
        await charge_point.on_transaction_event(
            event_type="Started",
            timestamp="2026-08-11T16:41:10Z",
            trigger_reason="Authorized",
            seq_no=0,
            transaction_info={"transaction_id": "CP201-TXN-002"},
            evse={"id": 1, "connector_id": 1},
        )

    mock_call.assert_awaited_once_with("TEST-CP-201", 1, "CP201-TXN-002", 0)


@pytest.mark.asyncio
async def test_transaction_event_updated_calls_meter_values_bridge(charge_point):
    with patch("app.charge_point_v201.bridge_client.send_meter_values", new=AsyncMock()) as mock_call:
        await charge_point.on_transaction_event(
            event_type="Updated",
            timestamp="2026-08-11T16:45:00Z",
            trigger_reason="MeterValuePeriodic",
            seq_no=1,
            transaction_info={"transaction_id": "CP201-TXN-001"},
            evse={"id": 1, "connector_id": 1},
            meter_value=[{
                "timestamp": "2026-08-11T16:45:00Z",
                "sampled_value": [{"value": 2.5, "measurand": "Energy.Active.Import.Register", "unit_of_measure": {"unit": "kWh"}}],
            }],
        )

    mock_call.assert_awaited_once_with("TEST-CP-201", "CP201-TXN-001", 2500)


@pytest.mark.asyncio
async def test_transaction_event_updated_skips_bridge_call_without_meter_value(charge_point):
    with patch("app.charge_point_v201.bridge_client.send_meter_values", new=AsyncMock()) as mock_call:
        await charge_point.on_transaction_event(
            event_type="Updated",
            timestamp="2026-08-11T16:45:00Z",
            trigger_reason="Other",
            seq_no=1,
            transaction_info={"transaction_id": "CP201-TXN-001"},
            evse={"id": 1, "connector_id": 1},
        )

    mock_call.assert_not_awaited()


@pytest.mark.asyncio
async def test_transaction_event_ended_calls_stop_bridge_with_mapped_reason(charge_point):
    with patch("app.charge_point_v201.bridge_client.send_stop_transaction", new=AsyncMock()) as mock_call:
        await charge_point.on_transaction_event(
            event_type="Ended",
            timestamp="2026-08-11T16:50:00Z",
            trigger_reason="EVDeparted",
            seq_no=2,
            transaction_info={"transaction_id": "CP201-TXN-001", "stopped_reason": "EVDisconnected"},
            evse={"id": 1, "connector_id": 1},
            meter_value=[{
                "timestamp": "2026-08-11T16:50:00Z",
                "sampled_value": [{"value": 3200.0, "measurand": "Energy.Active.Import.Register"}],
            }],
        )

    mock_call.assert_awaited_once_with("TEST-CP-201", "CP201-TXN-001", 3200, "vehicle_disconnected")


@pytest.mark.asyncio
async def test_transaction_event_still_acknowledges_when_bridge_call_fails(charge_point):
    with patch(
        "app.charge_point_v201.bridge_client.send_start_transaction_external",
        new=AsyncMock(side_effect=BridgeClientError(409, "Connector already occupied")),
    ):
        result = await charge_point.on_transaction_event(
            event_type="Started",
            timestamp="2026-08-11T16:41:10Z",
            trigger_reason="Authorized",
            seq_no=0,
            transaction_info={"transaction_id": "CP201-TXN-003"},
            evse={"id": 1, "connector_id": 1},
        )

    assert result is not None


@pytest.mark.parametrize("connector_status,expected", [
    ("Available", "available"),
    ("Occupied", "occupied"),
    ("Reserved", "occupied"),
    ("Unavailable", "out_of_service"),
    ("Faulted", "fault"),
    ("SomeUnknownStatus", "fault"),
])
def test_map_ocpp201_status(connector_status, expected):
    from app.charge_point_v201 import _map_ocpp201_status
    assert _map_ocpp201_status(connector_status) == expected
