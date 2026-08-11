def extract_energy_wh(meter_value: list[dict]) -> int | None:
    """
    OCPP 1.6's MeterValues carries a list of MeterValue entries, each with
    its own timestamp and a list of sampled_value readings (voltage, current,
    power, energy register, etc., depending on what the charger reports).

    Note: the ocpp library's routing layer recursively converts every key in
    the incoming payload from camelCase to snake_case before calling our
    handler (including nested structures), so the wire's "sampledValue"
    arrives here as "sampled_value" — not the raw OCPP spec name.

    This extracts a single integer Wh reading suitable for
    ChargingSessionService::recordMeterValue(), which expects one monotonic
    energy figure, not a raw telemetry dump — parsing the full sampled_value
    structure stays in the gateway (decision #3: raw OCPP payloads normalize
    into shared internal shapes before reaching Laravel).

    Strategy: take the most recent MeterValue entry (last in the list, per
    OCPP ordering), then within it prefer a sampled_value whose measurand is
    the cumulative energy register; fall back to the first sampled_value if
    no measurand is reported (some chargers omit it, and the spec default
    is the energy register). Converts kWh to Wh if that's the reported unit.

    Returns None if no usable reading is found, so the caller can decide
    whether to skip the bridge call entirely rather than send a bad value.
    """
    if not meter_value:
        return None

    latest_entry = meter_value[-1]
    sampled_values = latest_entry.get("sampled_value", [])

    if not sampled_values:
        return None

    chosen = None
    for sample in sampled_values:
        if sample.get("measurand") == "Energy.Active.Import.Register":
            chosen = sample
            break

    if chosen is None:
        chosen = sampled_values[0]

    try:
        raw_value = float(chosen["value"])
    except (KeyError, TypeError, ValueError):
        return None

    unit = chosen.get("unit", "Wh")
    if unit == "kWh":
        raw_value *= 1000

    return int(round(raw_value))


def extract_energy_wh_v201(meter_value: list[dict]) -> int | None:
    """
    OCPP 2.0.1 variant of extract_energy_wh(). Structurally similar, but
    two real differences confirmed against the ocpp==2.1.0 library:

    - sampled_value.value is already a float on the wire, not a numeric
      string (1.6 sends strings; 2.0.1's SampledValueType declares value as
      a genuine float).
    - the unit lives nested under sampled_value.unit_of_measure.unit rather
      than a flat sampled_value.unit key. If unit_of_measure is absent
      entirely, the OCPP 2.0.1 spec's default unit is Wh.
    """
    if not meter_value:
        return None

    latest_entry = meter_value[-1]
    sampled_values = latest_entry.get("sampled_value", [])

    if not sampled_values:
        return None

    chosen = None
    for sample in sampled_values:
        if sample.get("measurand") == "Energy.Active.Import.Register":
            chosen = sample
            break

    if chosen is None:
        chosen = sampled_values[0]

    try:
        raw_value = float(chosen["value"])
    except (KeyError, TypeError, ValueError):
        return None

    unit_of_measure = chosen.get("unit_of_measure") or {}
    unit = unit_of_measure.get("unit", "Wh")
    if unit == "kWh":
        raw_value *= 1000

    return int(round(raw_value))
