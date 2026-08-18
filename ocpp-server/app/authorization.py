import os
from abc import ABC, abstractmethod

from app import bridge_client
from app.bridge_client import BridgeClientError
from app.config import OCPP_AUTHORIZATION_PROVIDER


class AuthorizationProvider(ABC):
    """
    Decides whether a presented OCPP idTag is allowed to authorize a
    charging session. Deliberately abstract so the concrete provider can be
    swapped later (RfidAuthorizationProvider, Module 7) with no changes to
    the OCPP handler code that calls it.
    """

    @abstractmethod
    async def is_authorized(self, id_tag: str, ocpp_identifier: str) -> bool:
        raise NotImplementedError


class TestAuthorizationProvider(AuthorizationProvider):
    """
    Fixture-based provider for development and testing. Reads a
    comma-separated list of allowed idTags from the OCPP_TEST_ID_TAGS
    environment variable. Any tag not in that list is rejected.

    __test__ = False tells pytest not to treat this as a test class despite
    its name starting with "Test" — this name comes from the frozen OCPP
    roadmap (decision #8) and matches Laravel's own naming for the same
    concept, so it's kept as-is rather than renamed to dodge the collision.

    ocpp_identifier is accepted for interface compatibility but ignored —
    this fixture provider has no concept of per-station eligibility.
    """
    __test__ = False

    def __init__(self):
        raw = os.environ.get("OCPP_TEST_ID_TAGS", "")
        self._allowed_tags = {tag.strip() for tag in raw.split(",") if tag.strip()}

    async def is_authorized(self, id_tag: str, ocpp_identifier: str) -> bool:
        return id_tag in self._allowed_tags


class RfidAuthorizationProvider(AuthorizationProvider):
    """
    Production provider (Module 7). Pure HTTP adapter with no database
    access of its own — every authorization decision is delegated to
    Laravel's RfidAuthorizationService via POST /internal/ocpp/authorize,
    which is the sole source of authorization policy (credential validity,
    badge lifecycle state, owner eligibility, station organization scope).

    Fails closed: any transport-level failure (timeout, connection error,
    unexpected 5xx) is treated as NOT authorized rather than raised or
    retried. This is a deliberate real-time safety choice distinct from the
    _post_with_retry() used for transaction-state calls elsewhere in
    bridge_client.py — Authorize.req is not itself state-changing, so there
    is nothing to reconcile after the fact, and a driver at the charger
    should get a fast, safe rejection rather than a slow multi-attempt hang.
    """

    async def is_authorized(self, id_tag: str, ocpp_identifier: str) -> bool:
        try:
            result = await bridge_client.send_authorize(ocpp_identifier, id_tag)
        except BridgeClientError:
            return False

        return bool(result.get("accepted", False))


def build_authorization_provider() -> AuthorizationProvider:
    """
    Config-driven provider selection (OCPP_AUTHORIZATION_PROVIDER env var).
    Defaults to "test" so local dev/testing never accidentally exercises
    the real RFID authorization path unless explicitly opted in — the same
    safe-by-default posture the codebase already uses for other
    environment-driven settings.
    """
    if OCPP_AUTHORIZATION_PROVIDER == "rfid":
        return RfidAuthorizationProvider()

    return TestAuthorizationProvider()
