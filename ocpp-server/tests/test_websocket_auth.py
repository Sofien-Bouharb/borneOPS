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


def test_connection_rejected_when_authorized_false_covers_decommissioned_or_version_mismatch_too():
    # The gateway has no way to distinguish *why* Laravel returned
    # authorized: false (bad credential, decommissioned station, or
    # negotiated-version mismatch — see verify-station-credential's
    # `reason` field for that). Every one of those cases collapses to the
    # same bool here, and the gateway's job is simply to reject the
    # connection regardless of which reason it was. The specific reasons
    # are proven at the Laravel layer (OcppBridgeCredentialVerificationTest),
    # not here.
    with patch("app.main.bridge_client.verify_station_credential", new=AsyncMock(return_value=False)):
        with pytest.raises(Exception):
            with client.websocket_connect(
                "/ocpp/CP-DECOMMISSIONED", subprotocols=["ocpp1.6"], headers=_auth_header("CP-DECOMMISSIONED", "correct")
            ):
                pass


def test_verify_station_credential_is_called_with_negotiated_version_1_6():
    mock_verify = AsyncMock(return_value=True)

    with patch("app.main.bridge_client.verify_station_credential", new=mock_verify):
        with client.websocket_connect(
            "/ocpp/CP-001", subprotocols=["ocpp1.6"], headers=_auth_header("CP-001", "correct")
        ):
            pass

    mock_verify.assert_awaited_once_with("CP-001", "correct", "1.6")


def test_verify_station_credential_is_called_with_negotiated_version_2_0_1():
    mock_verify = AsyncMock(return_value=True)

    with patch("app.main.bridge_client.verify_station_credential", new=mock_verify):
        with client.websocket_connect(
            "/ocpp/CP201-001", subprotocols=["ocpp2.0.1"], headers=_auth_header("CP201-001", "correct")
        ):
            pass

    mock_verify.assert_awaited_once_with("CP201-001", "correct", "2.0.1")


def test_verify_station_credential_prefers_2_0_1_when_both_offered():
    mock_verify = AsyncMock(return_value=True)

    with patch("app.main.bridge_client.verify_station_credential", new=mock_verify):
        with client.websocket_connect(
            "/ocpp/CP-BOTH", subprotocols=["ocpp1.6", "ocpp2.0.1"], headers=_auth_header("CP-BOTH", "correct")
        ):
            pass

    mock_verify.assert_awaited_once_with("CP-BOTH", "correct", "2.0.1")
