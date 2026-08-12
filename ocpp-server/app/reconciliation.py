import json
import logging
from datetime import datetime, timezone
from pathlib import Path

logger = logging.getLogger("ocpp-gateway")

RECONCILIATION_LOG_PATH = Path(__file__).resolve().parent.parent / "pending_reconciliation.jsonl"


def log_unreconciled_stop(
    ocpp_identifier: str,
    ocpp_transaction_id: str,
    meter_stop_wh: int,
    reason_code: str,
    error: str,
) -> None:
    """
    OCPP 1.6's StopTransaction response (and 2.0.1's TransactionEvent Ended
    response) has no field for the CSMS to signal rejection — the charger
    must always receive a valid acknowledgment regardless of whether Laravel
    actually persisted the stop. If every retry attempt in
    bridge_client._post_with_retry() failed, this durably records the event
    to a local append-only file so it is never silently lost to a single log
    line, and can be reconciled later (manually, or by a future scheduled
    job that replays entries from this file against the bridge).
    """
    entry = {
        "logged_at": datetime.now(timezone.utc).isoformat(),
        "ocpp_identifier": ocpp_identifier,
        "ocpp_transaction_id": ocpp_transaction_id,
        "meter_stop_wh": meter_stop_wh,
        "reason_code": reason_code,
        "error": error,
    }

    logger.error(
        f"UNRECONCILED STOP TRANSACTION for {ocpp_identifier} "
        f"(transaction {ocpp_transaction_id}): {error}. Logged to {RECONCILIATION_LOG_PATH} "
        f"for manual reconciliation."
    )

    with open(RECONCILIATION_LOG_PATH, "a") as f:
        f.write(json.dumps(entry) + "\n")
