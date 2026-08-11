import pytest
from unittest.mock import AsyncMock, patch, MagicMock
from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def _headers():
    from app.config import OCPP_BRIDGE_TOKEN
    return {"X-OCPP-Bridge-Token": OCPP_BRIDGE_TOKEN}


class FakeResult:
    def __init__(self, status):
        self.status = status


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
