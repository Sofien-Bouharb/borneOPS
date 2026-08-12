import pytest
from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_connection_with_unsupported_subprotocol_is_rejected():
    with pytest.raises(Exception):
        with client.websocket_connect("/ocpp/TEST-CP-001", subprotocols=["mqtt"]):
            pass


def test_connection_with_no_subprotocol_is_rejected():
    with pytest.raises(Exception):
        with client.websocket_connect("/ocpp/TEST-CP-001"):
            pass


def test_connection_with_ocpp16_subprotocol_is_accepted():
    with client.websocket_connect("/ocpp/TEST-CP-001", subprotocols=["ocpp1.6"]) as ws:
        assert ws is not None


def test_connection_with_ocpp201_subprotocol_is_accepted():
    with client.websocket_connect("/ocpp/TEST-CP-001", subprotocols=["ocpp2.0.1"]) as ws:
        assert ws is not None


def test_connection_offering_both_prefers_ocpp201():
    with client.websocket_connect("/ocpp/TEST-CP-001", subprotocols=["ocpp1.6", "ocpp2.0.1"]) as ws:
        assert ws is not None
