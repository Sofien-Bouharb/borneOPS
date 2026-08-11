import logging

logger = logging.getLogger("ocpp-gateway")

_connections: dict[str, object] = {}


async def register(ocpp_identifier: str, charge_point) -> None:
    """
    Register a newly connected charge point. Per decision #19, if a charge
    point with the same ocpp_identifier is already connected, the newest
    connection wins — the previous WebSocket is closed.
    """
    existing = _connections.get(ocpp_identifier)

    if existing is not None:
        logger.warning(
            f"Duplicate connection for {ocpp_identifier}. Closing previous connection, newest wins."
        )
        try:
            await existing._connection._websocket.close()
        except Exception as e:
            logger.warning(f"Error closing previous connection for {ocpp_identifier}: {e}")

    _connections[ocpp_identifier] = charge_point


def unregister(ocpp_identifier: str, charge_point) -> None:
    """
    Remove a charge point from the registry, but only if it's still the
    currently registered one — this avoids a disconnecting old connection
    accidentally unregistering a newer one that has already taken its place.
    """
    if _connections.get(ocpp_identifier) is charge_point:
        del _connections[ocpp_identifier]


def get(ocpp_identifier: str):
    return _connections.get(ocpp_identifier)


def is_connected(ocpp_identifier: str) -> bool:
    return ocpp_identifier in _connections
