import pytest
from unittest.mock import AsyncMock, patch
from app.authorization import (
    TestAuthorizationProvider,
    RfidAuthorizationProvider,
    build_authorization_provider,
)
from app.bridge_client import BridgeClientError
from app.charge_point import BorneOpsChargePoint16


class FakeConnection:
    async def recv(self):
        raise NotImplementedError

    async def send(self, message):
        pass


@pytest.fixture
def charge_point():
    return BorneOpsChargePoint16("TEST-CP-001", FakeConnection())


@pytest.mark.asyncio
async def test_authorization_provider_accepts_configured_tag():
    with patch.dict("os.environ", {"OCPP_TEST_ID_TAGS": "TAG-A,TAG-B"}):
        provider = TestAuthorizationProvider()
    assert await provider.is_authorized("TAG-A", "TEST-CP-001") is True
    assert await provider.is_authorized("TAG-B", "TEST-CP-001") is True


@pytest.mark.asyncio
async def test_authorization_provider_rejects_unconfigured_tag():
    with patch.dict("os.environ", {"OCPP_TEST_ID_TAGS": "TAG-A,TAG-B"}):
        provider = TestAuthorizationProvider()
    assert await provider.is_authorized("TAG-UNKNOWN", "TEST-CP-001") is False


@pytest.mark.asyncio
async def test_authorization_provider_handles_empty_configuration():
    with patch.dict("os.environ", {"OCPP_TEST_ID_TAGS": ""}):
        provider = TestAuthorizationProvider()
    assert await provider.is_authorized("ANYTHING", "TEST-CP-001") is False


@pytest.mark.asyncio
async def test_authorization_provider_trims_whitespace_around_tags():
    with patch.dict("os.environ", {"OCPP_TEST_ID_TAGS": " TAG-A , TAG-B "}):
        provider = TestAuthorizationProvider()
    assert await provider.is_authorized("TAG-A", "TEST-CP-001") is True
    assert await provider.is_authorized("TAG-B", "TEST-CP-001") is True


@pytest.mark.asyncio
async def test_on_authorize_accepts_valid_tag(charge_point):
    charge_point.authorization_provider.is_authorized = AsyncMock(return_value=True)
    result = await charge_point.on_authorize(id_tag="TAG-DRIVER-001")
    assert result.id_tag_info.status == "Accepted"


@pytest.mark.asyncio
async def test_on_authorize_rejects_invalid_tag(charge_point):
    charge_point.authorization_provider.is_authorized = AsyncMock(return_value=False)
    result = await charge_point.on_authorize(id_tag="TAG-UNKNOWN")
    assert result.id_tag_info.status == "Invalid"


@pytest.mark.asyncio
async def test_rfid_authorization_provider_accepts_when_bridge_accepts():
    provider = RfidAuthorizationProvider()

    with patch(
        "app.authorization.bridge_client.send_authorize",
        new=AsyncMock(return_value={"accepted": True, "reason": "accepted"}),
    ):
        result = await provider.is_authorized("RAW-TOKEN", "CP-001")

    assert result is True


@pytest.mark.asyncio
async def test_rfid_authorization_provider_rejects_when_bridge_rejects():
    provider = RfidAuthorizationProvider()

    with patch(
        "app.authorization.bridge_client.send_authorize",
        new=AsyncMock(return_value={"accepted": False, "reason": "badge_blocked"}),
    ):
        result = await provider.is_authorized("RAW-TOKEN", "CP-001")

    assert result is False


@pytest.mark.asyncio
async def test_rfid_authorization_provider_fails_closed_on_bridge_error():
    provider = RfidAuthorizationProvider()

    with patch(
        "app.authorization.bridge_client.send_authorize",
        new=AsyncMock(side_effect=BridgeClientError(503, "Could not reach Laravel bridge")),
    ):
        result = await provider.is_authorized("RAW-TOKEN", "CP-001")

    assert result is False


def test_build_authorization_provider_defaults_to_test_provider():
    with patch.dict("os.environ", {}, clear=False):
        with patch("app.authorization.OCPP_AUTHORIZATION_PROVIDER", "test"):
            provider = build_authorization_provider()
    assert isinstance(provider, TestAuthorizationProvider)


def test_build_authorization_provider_selects_rfid_provider():
    with patch("app.authorization.OCPP_AUTHORIZATION_PROVIDER", "rfid"):
        provider = build_authorization_provider()
    assert isinstance(provider, RfidAuthorizationProvider)
