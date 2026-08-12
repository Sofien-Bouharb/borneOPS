import base64
from unittest.mock import AsyncMock, patch

import pytest
from fastapi.testclient import TestClient

from app.main import app
from app.bridge_client import BridgeClientError

client = TestClient(app)


def _auth_header(username: str, password: str) -> dict:
    token = base64.b64encode(f"{username}:{password}".encode()).decode()
    return {"Authorization": f"Basic {token}"}


def test_connection_without_credential_is_rejected():
    with pytest.raises(Exception):
        with client.websocket_connect("/ocpp/CP-001", subprotocols=["ocpp1.6"]):
            pass


def test_connection_with_invalid_credential_is_rejected():
    with patch("app.main.bridge_client.verify_station_credential", new=AsyncMock(return_value=False)):
        with pytest.raises(Exception):
            with client.websocket_connect(
                "/ocpp/CP-001", subprotocols=["ocpp1.6"], headers=_auth_header("CP-001", "wrong")
            ):
                pass


def test_connection_with_valid_credential_is_accepted():
    with patch("app.main.bridge_client.verify_station_credential", new=AsyncMock(return_value=True)):
        with client.websocket_connect(
            "/ocpp/CP-001", subprotocols=["ocpp1.6"], headers=_auth_header("CP-001", "correct")
        ) as ws:
            assert ws is not None


def test_connection_fails_closed_when_verification_call_fails():
    with patch(
        "app.main.bridge_client.verify_station_credential",
        new=AsyncMock(side_effect=BridgeClientError(503, "Could not reach Laravel bridge")),
    ):
        with pytest.raises(Exception):
            with client.websocket_connect(
                "/ocpp/CP-001", subprotocols=["ocpp1.6"], headers=_auth_header("CP-001", "correct")
            ):
                pass
