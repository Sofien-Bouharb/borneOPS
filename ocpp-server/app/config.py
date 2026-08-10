from dotenv import load_dotenv
import os

load_dotenv()

LARAVEL_BASE_URL: str = os.environ.get("LARAVEL_BASE_URL", "http://127.0.0.1:8000")
OCPP_BRIDGE_TOKEN: str = os.environ.get("OCPP_BRIDGE_TOKEN", "")

if not OCPP_BRIDGE_TOKEN:
    raise RuntimeError(
        "OCPP_BRIDGE_TOKEN is not set. Create ocpp-server/.env with a value "
        "matching the Laravel application's OCPP_BRIDGE_TOKEN."
    )
