from app.meter_values import extract_energy_wh_v201


def test_extracts_energy_register_in_wh_default_unit():
    meter_value = [
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [
                {"value": 5500.0, "measurand": "Energy.Active.Import.Register"},
            ],
        }
    ]
    assert extract_energy_wh_v201(meter_value) == 5500


def test_converts_kwh_to_wh_via_nested_unit_of_measure():
    meter_value = [
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [
                {
                    "value": 5.5,
                    "measurand": "Energy.Active.Import.Register",
                    "unit_of_measure": {"unit": "kWh"},
                },
            ],
        }
    ]
    assert extract_energy_wh_v201(meter_value) == 5500


def test_prefers_energy_register_measurand_among_multiple_samples():
    meter_value = [
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [
                {"value": 230.0, "measurand": "Voltage"},
                {"value": 5500.0, "measurand": "Energy.Active.Import.Register"},
            ],
        }
    ]
    assert extract_energy_wh_v201(meter_value) == 5500


def test_falls_back_to_first_sample_when_no_measurand_present():
    meter_value = [
        {
            "timestamp": "2026-08-11T15:00:00Z",
            "sampled_value": [{"value": 4200.0}],
        }
    ]
    assert extract_energy_wh_v201(meter_value) == 4200


def test_uses_most_recent_meter_value_entry():
    meter_value = [
        {"timestamp": "2026-08-11T14:00:00Z", "sampled_value": [{"value": 1000.0, "measurand": "Energy.Active.Import.Register"}]},
        {"timestamp": "2026-08-11T15:00:00Z", "sampled_value": [{"value": 2000.0, "measurand": "Energy.Active.Import.Register"}]},
    ]
    assert extract_energy_wh_v201(meter_value) == 2000


def test_returns_none_for_empty_meter_value_list():
    assert extract_energy_wh_v201([]) is None


def test_returns_none_for_empty_sampled_value_list():
    meter_value = [{"timestamp": "2026-08-11T15:00:00Z", "sampled_value": []}]
    assert extract_energy_wh_v201(meter_value) is None


def test_returns_none_when_value_key_missing():
    meter_value = [{"timestamp": "2026-08-11T15:00:00Z", "sampled_value": [{"measurand": "Energy.Active.Import.Register"}]}]
    assert extract_energy_wh_v201(meter_value) is None
