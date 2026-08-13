import base64

from app.basic_auth import parse_basic_auth


def _encode(username: str, password: str) -> str:
    return "Basic " + base64.b64encode(f"{username}:{password}".encode()).decode()


def test_parses_valid_basic_auth_header():
    header = _encode("TEST-CP-001", "secret-password")
    result = parse_basic_auth(header)
    assert result == ("TEST-CP-001", "secret-password")


def test_returns_none_for_missing_header():
    assert parse_basic_auth(None) is None
    assert parse_basic_auth("") is None


def test_returns_none_for_non_basic_scheme():
    header = "Bearer sometoken"
    assert parse_basic_auth(header) is None


def test_returns_none_for_malformed_base64():
    assert parse_basic_auth("Basic not-valid-base64!!!") is None


def test_returns_none_when_no_colon_separator():
    encoded = base64.b64encode(b"no-colon-here").decode()
    assert parse_basic_auth(f"Basic {encoded}") is None


def test_password_can_contain_colons():
    header = _encode("TEST-CP-001", "pass:word:with:colons")
    result = parse_basic_auth(header)
    assert result == ("TEST-CP-001", "pass:word:with:colons")
