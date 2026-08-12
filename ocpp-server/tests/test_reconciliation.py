import json

import pytest

from app.reconciliation import log_unreconciled_stop, RECONCILIATION_LOG_PATH


@pytest.fixture(autouse=True)
def clean_reconciliation_log():
    if RECONCILIATION_LOG_PATH.exists():
        RECONCILIATION_LOG_PATH.unlink()
    yield
    if RECONCILIATION_LOG_PATH.exists():
        RECONCILIATION_LOG_PATH.unlink()


def test_log_unreconciled_stop_writes_a_json_line():
    log_unreconciled_stop("CP-001", "42", 5000, "user_requested", "Bridge call failed (503): timeout")

    assert RECONCILIATION_LOG_PATH.exists()
    lines = RECONCILIATION_LOG_PATH.read_text().strip().split("\n")
    assert len(lines) == 1

    entry = json.loads(lines[0])
    assert entry["ocpp_identifier"] == "CP-001"
    assert entry["ocpp_transaction_id"] == "42"
    assert entry["meter_stop_wh"] == 5000
    assert entry["reason_code"] == "user_requested"
    assert "logged_at" in entry


def test_log_unreconciled_stop_appends_multiple_entries():
    log_unreconciled_stop("CP-001", "1", 1000, "other", "error 1")
    log_unreconciled_stop("CP-002", "2", 2000, "other", "error 2")

    lines = RECONCILIATION_LOG_PATH.read_text().strip().split("\n")
    assert len(lines) == 2
