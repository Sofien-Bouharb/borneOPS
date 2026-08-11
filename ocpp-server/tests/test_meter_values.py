from app.meter_values import extract_energy_wh


def test_extracts_energy_register_in_wh():
    meter_value = [
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [
                {"value": "5500", "measurand": "Energy.Active.Import.Register", "unit": "Wh"},
            ],
        }
    ]
    assert extract_energy_wh(meter_value) == 5500


def test_converts_kwh_to_wh():
    meter_value = [
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [
                {"value": "5.5", "measurand": "Energy.Active.Import.Register", "unit": "kWh"},
            ],
        }
    ]
    assert extract_energy_wh(meter_value) == 5500


def test_prefers_energy_register_measurand_among_multiple_samples():
    meter_value = [
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [
                {"value": "230", "measurand": "Voltage", "unit": "V"},
                {"value": "5500", "measurand": "Energy.Active.Import.Register", "unit": "Wh"},
                {"value": "16", "measurand": "Current.Import", "unit": "A"},
            ],
        }
    ]
    assert extract_energy_wh(meter_value) == 5500


def test_falls_back_to_first_sample_when_no_measurand_present():
    meter_value = [
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [
                {"value": "4200"},
            ],
        }
    ]
    assert extract_energy_wh(meter_value) == 4200


def test_uses_most_recent_meter_value_entry():
    meter_value = [
        {
            "timestamp": "2026-08-11T14:00:00Z",
            "sampled_value": [{"value": "1000", "measurand": "Energy.Active.Import.Register", "unit": "Wh"}],
        },
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [{"value": "2000", "measurand": "Energy.Active.Import.Register", "unit": "Wh"}],
        },
    ]
    assert extract_energy_wh(meter_value) == 2000


def test_returns_none_for_empty_meter_value_list():
    assert extract_energy_wh([]) is None


def test_returns_none_for_empty_sampled_value_list():
    meter_value = [{"timestamp": "2026-08-11T15:00:00Z", "sampled_value": []}]
    assert extract_energy_wh(meter_value) is None


def test_returns_none_for_non_numeric_value():
    meter_value = [
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [{"value": "not-a-number", "unit": "Wh"}],
        }
    ]
    assert extract_energy_wh(meter_value) is None


def test_rounds_fractional_wh_to_nearest_integer():
    meter_value = [
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [{"value": "5500.6", "measurand": "Energy.Active.Import.Register", "unit": "Wh"}],
        }
    ]
    assert extract_energy_wh(meter_value) == 5501
