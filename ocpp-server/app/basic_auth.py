import base64


def parse_basic_auth(authorization_header: str | None) -> tuple[str, str] | None:
    """
    Parses an HTTP Basic Auth 'Authorization' header value into
    (username, password). Returns None if the header is missing or
    malformed in any way — callers should treat that as "no credential
    presented", not attempt to guess intent.
    """
    if not authorization_header:
        return None

    parts = authorization_header.split(" ", 1)
    if len(parts) != 2 or parts[0].lower() != "basic":
        return None

    try:
        decoded = base64.b64decode(parts[1]).decode("utf-8")
    except Exception:
        return None

    if ":" not in decoded:
        return None

    username, _, password = decoded.partition(":")
    return username, password
