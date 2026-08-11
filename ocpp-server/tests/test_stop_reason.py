from app.stop_reason import map_stop_reason


def test_ev_disconnected_maps_to_vehicle_disconnected():
    assert map_stop_reason("EVDisconnected") == "vehicle_disconnected"


def test_power_loss_maps_to_power_loss():
    assert map_stop_reason("PowerLoss") == "power_loss"


def test_remote_maps_to_remote_stop():
    assert map_stop_reason("Remote") == "remote_stop"


def test_local_maps_to_user_requested():
    assert map_stop_reason("Local") == "user_requested"


def test_unlock_command_maps_to_operator_requested():
    assert map_stop_reason("UnlockCommand") == "operator_requested"


def test_emergency_stop_maps_to_equipment_fault():
    assert map_stop_reason("EmergencyStop") == "equipment_fault"


def test_unmapped_reasons_fall_back_to_other():
    assert map_stop_reason("HardReset") == "other"
    assert map_stop_reason("SoftReset") == "other"
    assert map_stop_reason("Reboot") == "other"
    assert map_stop_reason("DeAuthorized") == "other"
    assert map_stop_reason("Other") == "other"


def test_missing_reason_falls_back_to_other():
    assert map_stop_reason(None) == "other"
