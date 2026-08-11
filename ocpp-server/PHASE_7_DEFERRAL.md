# Phase 7 — Auxiliary Transport (ChangeConfiguration / Firmware / Diagnostics)

## Status: Deferred

Per the frozen OCPP Integration Roadmap v1.1, decision #15 explicitly designates Phase 7 as the
**only** phase allowed to be compressed or deferred, on the condition that the deferral is named
here rather than silently dropped.

## What was already built (Phase 6, not part of this deferral)

- `Reset` (Hard/Soft) — full transport + Laravel-triggered endpoint, tested.
- `UnlockConnector` — full transport + Laravel-triggered endpoint, tested.
- `RemoteStartTransaction` / `RemoteStopTransaction` — full transport + Laravel-triggered endpoints, tested.

These four are the ones decision #12/#14 required to be functionally complete now.

## What is deferred

- `ChangeConfiguration` / `GetConfiguration` (OCPP 1.6) and their 2.0.1 equivalents
  (`SetVariables` / `GetVariables`) — no gateway command endpoint, no Laravel trigger endpoint,
  no UI.
- Firmware update commands (`UpdateFirmware`, `FirmwareStatusNotification` handling) — not built.
- Diagnostics commands (`GetDiagnostics`, `DiagnosticsStatusNotification` handling) — not built.

Per decision #14, these were always scoped to "transport-level hooks only, no business UI or
persistence" even at full scope — meaning even a completed Phase 7 would not have added
station-side UI or database columns for these, only the ability to send/receive the raw OCPP
messages. Deferring them entirely means BorneOPS cannot yet remotely reconfigure a station,
push firmware, or pull diagnostics logs through the gateway. A charger sending an unsolicited
`FirmwareStatusNotification` or `DiagnosticsStatusNotification` today will hit the gateway's
default "no handler registered for this action" behavior from the underlying `ocpp` library,
which returns a `NotImplemented` CallError — a protocol-correct response, not a crash.

## Why deferred

Time constraint on the internship schedule. Decisions #12 and #14 already identified this as the
lowest-priority phase precisely so it could absorb schedule pressure without threatening the
critical path (Phases 4-5, sessions) or the newly-completed Phase 6 (remote control), both of
which are fully built, tested, and verified end-to-end.

## Revisiting this later

If/when Phase 7 is picked up, the existing `app/commands.py` router and `OcppGatewayClient`
service establish the exact pattern to extend - a new command function in `bridge_client.py`
(if a Laravel-triggered flow), or a new `@on(...)` handler in `charge_point.py`/`charge_point_v201.py`
(if charger-initiated), following the same structure as `Reset`/`UnlockConnector`.
