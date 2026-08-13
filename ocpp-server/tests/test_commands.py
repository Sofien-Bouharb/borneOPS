import asyncio
import pytest
from unittest.mock import AsyncMock, patch, MagicMock
from fastapi.testclient import TestClient

from app.main import app
from app.charge_point_v201 import BorneOpsChargePoint201
from ocpp.v201 import call as call201

client = TestClient(app)


def _headers():
    from app.config import OCPP_BRIDGE_TOKEN
    return {"X-OCPP-Bridge-Token": OCPP_BRIDGE_TOKEN}


class FakeResult:
    def __init__(self, status):
        self.status = status


def _fake_v201_charge_point():
    # spec=BorneOpsChargePoint201 makes isinstance(fake_cp, BorneOpsChargePoint201)
    # true, which is exactly what app.commands._is_v201() checks to decide
    # which protocol's messages to send.
    fake_cp = MagicMock(spec=BorneOpsChargePoint201)
    fake_cp.call = AsyncMock(return_value=FakeResult("Accepted"))
    return fake_cp


def test_remote_start_requires_valid_token():
    response = client.post("/commands/CP-001/remote-start", json={"id_tag": "TAG-1"})
    assert response.status_code == 401


def test_remote_start_returns_404_when_not_connected():
    with patch("app.commands.registry.get", return_value=None):
        response = client.post("/commands/CP-DOES-NOT-EXIST/remote-start", json={"id_tag": "TAG-1"}, headers=_headers())

    assert response.status_code == 404


def test_remote_start_calls_charge_point_and_returns_status():
    fake_cp = MagicMock()
    fake_cp.call = AsyncMock(return_value=FakeResult("Accepted"))

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP-001/remote-start",
            json={"id_tag": "TAG-1", "connector_number": 1},
            headers=_headers(),
        )

    assert response.status_code == 200
    assert response.json() == {"status": "Accepted"}
    fake_cp.call.assert_awaited_once()
    sent_message = fake_cp.call.call_args.args[0]
    assert sent_message.__class__.__name__ == "RemoteStartTransaction"


def test_remote_start_sends_request_start_transaction_for_v201_connection():
    fake_cp = _fake_v201_charge_point()

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP201-001/remote-start",
            json={"id_tag": "TAG-DRIVER-001", "connector_number": 1},
            headers=_headers(),
        )

    assert response.status_code == 200
    assert response.json() == {"status": "Accepted"}
    fake_cp.call.assert_awaited_once()
    sent_message = fake_cp.call.call_args.args[0]
    assert isinstance(sent_message, call201.RequestStartTransaction)
    assert sent_message.id_token.id_token == "TAG-DRIVER-001"
    assert sent_message.evse_id == 1
    assert isinstance(sent_message.remote_start_id, int)


def test_remote_start_returns_504_when_charge_point_does_not_respond_in_time():
    fake_cp = MagicMock()
    fake_cp.call = AsyncMock(side_effect=asyncio.TimeoutError())

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP-001/remote-start",
            json={"id_tag": "TAG-1", "connector_number": 1},
            headers=_headers(),
        )

    assert response.status_code == 504


def test_remote_stop_rejects_non_numeric_transaction_id():
    fake_cp = MagicMock()
    fake_cp.call = AsyncMock()

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP-001/remote-stop",
            json={"ocpp_transaction_id": "CP201-STYLE-STRING"},
            headers=_headers(),
        )

    assert response.status_code == 422
    fake_cp.call.assert_not_awaited()


def test_remote_stop_calls_charge_point_with_numeric_id():
    fake_cp = MagicMock()
    fake_cp.call = AsyncMock(return_value=FakeResult("Accepted"))

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP-001/remote-stop",
            json={"ocpp_transaction_id": "42"},
            headers=_headers(),
        )

    assert response.status_code == 200
    assert response.json() == {"status": "Accepted"}


def test_remote_stop_accepts_non_numeric_transaction_id_for_v201_connection():
    fake_cp = _fake_v201_charge_point()

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP201-001/remote-stop",
            json={"ocpp_transaction_id": "CP201-STYLE-STRING"},
            headers=_headers(),
        )

    assert response.status_code == 200
    fake_cp.call.assert_awaited_once()
    sent_message = fake_cp.call.call_args.args[0]
    assert isinstance(sent_message, call201.RequestStopTransaction)
    assert sent_message.transaction_id == "CP201-STYLE-STRING"


def test_remote_stop_returns_504_when_charge_point_does_not_respond_in_time():
    fake_cp = MagicMock()
    fake_cp.call = AsyncMock(side_effect=asyncio.TimeoutError())

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP-001/remote-stop",
            json={"ocpp_transaction_id": "42"},
            headers=_headers(),
        )

    assert response.status_code == 504


def test_reset_rejects_invalid_type():
    fake_cp = MagicMock()
    fake_cp.call = AsyncMock()

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP-001/reset",
            json={"type": "NotARealType"},
            headers=_headers(),
        )

    assert response.status_code == 422
    fake_cp.call.assert_not_awaited()


def test_reset_calls_charge_point_and_returns_status():
    fake_cp = MagicMock()
    fake_cp.call = AsyncMock(return_value=FakeResult("Accepted"))

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP-001/reset",
            json={"type": "Soft"},
            headers=_headers(),
        )

    assert response.status_code == 200
    assert response.json() == {"status": "Accepted"}


@pytest.mark.parametrize("legacy_type,expected_v201_value", [
    ("Hard", "Immediate"),
    ("Soft", "OnIdle"),
])
def test_reset_maps_legacy_type_to_v201_enum(legacy_type, expected_v201_value):
    fake_cp = _fake_v201_charge_point()

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP201-001/reset",
            json={"type": legacy_type},
            headers=_headers(),
        )

    assert response.status_code == 200
    sent_message = fake_cp.call.call_args.args[0]
    assert isinstance(sent_message, call201.Reset)
    assert sent_message.type.value == expected_v201_value


def test_reset_returns_504_when_charge_point_does_not_respond_in_time():
    fake_cp = MagicMock()
    fake_cp.call = AsyncMock(side_effect=asyncio.TimeoutError())

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP-001/reset",
            json={"type": "Soft"},
            headers=_headers(),
        )

    assert response.status_code == 504


def test_unlock_connector_calls_charge_point_and_returns_status():
    fake_cp = MagicMock()
    fake_cp.call = AsyncMock(return_value=FakeResult("Unlocked"))

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP-001/unlock-connector",
            json={"connector_number": 1},
            headers=_headers(),
        )

    assert response.status_code == 200
    assert response.json() == {"status": "Unlocked"}


def test_unlock_connector_sends_evse_and_connector_id_for_v201_connection():
    fake_cp = _fake_v201_charge_point()
    fake_cp.call = AsyncMock(return_value=FakeResult("Unlocked"))

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP201-001/unlock-connector",
            json={"connector_number": 1, "evse_id": 2, "connector_id": 1},
            headers=_headers(),
        )

    assert response.status_code == 200
    assert response.json() == {"status": "Unlocked"}
    sent_message = fake_cp.call.call_args.args[0]
    assert isinstance(sent_message, call201.UnlockConnector)
    assert sent_message.evse_id == 2
    assert sent_message.connector_id == 1


def test_unlock_connector_rejects_v201_connection_missing_evse_id():
    fake_cp = _fake_v201_charge_point()

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP201-001/unlock-connector",
            json={"connector_number": 1, "connector_id": 1},
            headers=_headers(),
        )

    assert response.status_code == 422
    fake_cp.call.assert_not_awaited()


def test_unlock_connector_returns_504_when_charge_point_does_not_respond_in_time():
    fake_cp = MagicMock()
    fake_cp.call = AsyncMock(side_effect=asyncio.TimeoutError())

    with patch("app.commands.registry.get", return_value=fake_cp):
        response = client.post(
            "/commands/CP-001/unlock-connector",
            json={"connector_number": 1},
            headers=_headers(),
        )

    assert response.status_code == 504
