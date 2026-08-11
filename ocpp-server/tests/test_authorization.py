import pytest
from unittest.mock import patch

from app.authorization import TestAuthorizationProvider
from app.charge_point import BorneOpsChargePoint16


class FakeConnection:
    async def recv(self):
        raise NotImplementedError

    async def send(self, message):
        pass


@pytest.fixture
def charge_point():
    return BorneOpsChargePoint16("TEST-CP-001", FakeConnection())


def test_authorization_provider_accepts_configured_tag():
    with patch.dict("os.environ", {"OCPP_TEST_ID_TAGS": "TAG-A,TAG-B"}):
        provider = TestAuthorizationProvider()

    assert provider.is_authorized("TAG-A") is True
    assert provider.is_authorized("TAG-B") is True


def test_authorization_provider_rejects_unconfigured_tag():
    with patch.dict("os.environ", {"OCPP_TEST_ID_TAGS": "TAG-A,TAG-B"}):
        provider = TestAuthorizationProvider()

    assert provider.is_authorized("TAG-UNKNOWN") is False


def test_authorization_provider_handles_empty_configuration():
    with patch.dict("os.environ", {"OCPP_TEST_ID_TAGS": ""}):
        provider = TestAuthorizationProvider()

    assert provider.is_authorized("ANYTHING") is False


def test_authorization_provider_trims_whitespace_around_tags():
    with patch.dict("os.environ", {"OCPP_TEST_ID_TAGS": " TAG-A , TAG-B "}):
        provider = TestAuthorizationProvider()

    assert provider.is_authorized("TAG-A") is True
    assert provider.is_authorized("TAG-B") is True


@pytest.mark.asyncio
async def test_on_authorize_accepts_valid_tag(charge_point):
    charge_point.authorization_provider.is_authorized = lambda tag: True

    result = await charge_point.on_authorize(id_tag="TAG-DRIVER-001")

    assert result.id_tag_info.status == "Accepted"


@pytest.mark.asyncio
async def test_on_authorize_rejects_invalid_tag(charge_point):
    charge_point.authorization_provider.is_authorized = lambda tag: False

    result = await charge_point.on_authorize(id_tag="TAG-UNKNOWN")

    assert result.id_tag_info.status == "Invalid"
