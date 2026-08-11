import pytest
from unittest.mock import AsyncMock, MagicMock

from app import registry


class FakeChargePoint:
    def __init__(self, ws_close_mock=None):
        self._connection = MagicMock()
        self._connection._websocket.close = ws_close_mock or AsyncMock()


@pytest.fixture(autouse=True)
def clear_registry():
    registry._connections.clear()
    yield
    registry._connections.clear()


@pytest.mark.asyncio
async def test_register_stores_the_charge_point():
    cp = FakeChargePoint()
    await registry.register("CP-001", cp)

    assert registry.get("CP-001") is cp
    assert registry.is_connected("CP-001") is True


@pytest.mark.asyncio
async def test_register_closes_previous_connection_on_duplicate():
    old_close = AsyncMock()
    old_cp = FakeChargePoint(ws_close_mock=old_close)
    new_cp = FakeChargePoint()

    await registry.register("CP-001", old_cp)
    await registry.register("CP-001", new_cp)

    old_close.assert_awaited_once()
    assert registry.get("CP-001") is new_cp


def test_unregister_removes_current_charge_point():
    cp = FakeChargePoint()
    registry._connections["CP-001"] = cp

    registry.unregister("CP-001", cp)

    assert registry.is_connected("CP-001") is False


def test_unregister_does_not_remove_a_newer_connection():
    old_cp = FakeChargePoint()
    new_cp = FakeChargePoint()
    registry._connections["CP-001"] = new_cp

    registry.unregister("CP-001", old_cp)

    assert registry.get("CP-001") is new_cp


def test_get_returns_none_for_unknown_identifier():
    assert registry.get("CP-DOES-NOT-EXIST") is None


def test_is_connected_returns_false_for_unknown_identifier():
    assert registry.is_connected("CP-DOES-NOT-EXIST") is False
