# BorneOPS OCPP Gateway

Standalone Python process implementing the OCPP 1.6 / 2.0.1 WebSocket server (CSMS) for BorneOPS.
Runs independently from the Laravel application and Reverb — see the frozen OCPP Integration Roadmap
(v1.1 Final) for the full architecture.

## Setup

```bash
cd ocpp-server
python3.12 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

## Running the gateway (development)

```bash
source venv/bin/activate
uvicorn app.main:app --reload --port 8001
```

The gateway runs alongside, not instead of, the usual Laravel development processes:

```bash
php artisan serve
php artisan reverb:start
npm run dev
uvicorn app.main:app --reload --port 8001   # from ocpp-server/, venv activated
```

## Health check

```bash
curl http://127.0.0.1:8001/health
```

## Running tests

```bash
source venv/bin/activate
pytest
```

## Notes

- This process has no direct database access. All business writes reach Laravel exclusively through
  the internal REST bridge (`/api/internal/ocpp/events/*`, `/api/internal/ocpp/transactions/*`),
  authenticated by a dedicated `OCPP_BRIDGE_TOKEN` service secret — never a human JWT.
- Never vendor the SAP e-mobility-charging-stations-simulator into this repo; it is an external
  dev/E2E testing tool only.
