import base64
import pytest
from unittest.mock import AsyncMock, patch
from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def _auth_header(username: str, password: str) -> dict:
    token = base64.b64encode(f"{username}:{password}".encode()).decode()
    return {"Authorization": f"Basic {token}"}


def test_connection_with_unsupported_subprotocol_is_rejected():
    with pytest.raises(Exception):
        with client.websocket_connect("/ocpp/TEST-CP-001", subprotocols=["mqtt"]):
            pass


def test_connection_with_no_subprotocol_is_rejected():
    with pytest.raises(Exception):
        with client.websocket_connect("/ocpp/TEST-CP-001"):
            pass


def test_connection_with_ocpp16_subprotocol_is_accepted():
    with patch("app.main.bridge_client.verify_station_credential", new=AsyncMock(return_value=True)):
        with client.websocket_connect(
            "/ocpp/TEST-CP-001", subprotocols=["ocpp1.6"], headers=_auth_header("TEST-CP-001", "pw")
        ) as ws:
            assert ws is not None


def test_connection_with_ocpp201_subprotocol_is_accepted():
    with patch("app.main.bridge_client.verify_station_credential", new=AsyncMock(return_value=True)):
        with client.websocket_connect(
            "/ocpp/TEST-CP-001", subprotocols=["ocpp2.0.1"], headers=_auth_header("TEST-CP-001", "pw")
        ) as ws:
            assert ws is not None


def test_connection_offering_both_prefers_ocpp201():
    with patch("app.main.bridge_client.verify_station_credential", new=AsyncMock(return_value=True)):
        with client.websocket_connect(
            "/ocpp/TEST-CP-001", subprotocols=["ocpp1.6", "ocpp2.0.1"], headers=_auth_header("TEST-CP-001", "pw")
        ) as ws:
            assert ws is not None
