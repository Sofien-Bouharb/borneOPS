import os
from abc import ABC, abstractmethod


class AuthorizationProvider(ABC):
    """
    Decides whether a presented OCPP idTag is allowed to authorize a
    charging session. Deliberately abstract so the concrete provider can be
    swapped later (RfidAuthorizationProvider, Module 7) with no changes to
    the OCPP handler code that calls it.
    """

    @abstractmethod
    def is_authorized(self, id_tag: str) -> bool:
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
    """

    __test__ = False

    def __init__(self):
        raw = os.environ.get("OCPP_TEST_ID_TAGS", "")
        self._allowed_tags = {tag.strip() for tag in raw.split(",") if tag.strip()}

    def is_authorized(self, id_tag: str) -> bool:
        return id_tag in self._allowed_tags
