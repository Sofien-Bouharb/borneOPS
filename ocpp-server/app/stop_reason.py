def map_stop_reason(ocpp_reason: str | None) -> str:
    """
    Maps OCPP 1.6's StopTransaction.reason (the Reason enum) to BorneOPS's
    ChargingSessionService::COMPLETION_REASON_CODES. Any value with no clear
    BorneOPS equivalent, or a missing reason entirely (the OCPP field is
    optional), maps to 'other' rather than guessing.
    """
    mapping = {
        "EVDisconnected": "vehicle_disconnected",
        "PowerLoss": "power_loss",
        "Remote": "remote_stop",
        "Local": "user_requested",
        "UnlockCommand": "operator_requested",
        "EmergencyStop": "equipment_fault",
    }
    return mapping.get(ocpp_reason, "other")
