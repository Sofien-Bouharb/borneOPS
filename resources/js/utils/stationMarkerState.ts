import { SupervisionStation } from '../api/supervision';

export type MarkerSeverity = 'critical' | 'warning' | 'info' | 'ok' | 'muted';

export interface MarkerState {
    severity: MarkerSeverity;
    color: string;
    label: string;
    hasConnectorMismatch: boolean;
}

const SEVERITY_COLORS: Record<MarkerSeverity, string> = {
    critical: '#a8071a',
    warning: '#d46b08',
    info: '#12629C',
    ok: '#157A6E',
    muted: '#8c8c8c',
};

/**
 * Determine a single, unambiguous visual marker state for a station,
 * given that administrative_status, operational_status, and
 * connection_status are independent axes that can all be true at once.
 *
 * Precedence (highest wins), from least to most urgent to override:
 *   1. decommissioned    — intentionally retired, not a live concern
 *   2. commissioning     — not live yet, "disconnected" here is expected
 *   3. disabled           — deliberately turned off by an administrator
 *   4. connection lost    — a live station gone silent (most urgent)
 *   5. operational fault  — a live station reporting a hardware fault
 *   6. maintenance / out_of_service — known, expected, still worth flagging
 *   7. occupied            — normal, in active use
 *   8. everything else     — healthy
 *
 * Connector count mismatch is deliberately NOT folded into this precedence —
 * it's a data-quality signal, not an operational severity, and is surfaced
 * separately via hasConnectorMismatch so callers can render it as a
 * secondary badge rather than overriding the primary marker color.
 */
export function getStationMarkerState(station: SupervisionStation): MarkerState {
    const hasConnectorMismatch = station.connector_count_matches === false;

    if (station.administrative_status === 'decommissioned') {
        return {
            severity: 'muted',
            color: SEVERITY_COLORS.muted,
            label: 'Décommissionnée',
            hasConnectorMismatch,
        };
    }

    if (station.administrative_status === 'commissioning') {
        return {
            severity: 'info',
            color: SEVERITY_COLORS.info,
            label: 'En mise en service',
            hasConnectorMismatch,
        };
    }

    if (station.administrative_status === 'disabled') {
        return {
            severity: 'warning',
            color: SEVERITY_COLORS.warning,
            label: 'Désactivée',
            hasConnectorMismatch,
        };
    }

    if (station.connection_status === 'disconnected') {
        return {
            severity: 'critical',
            color: SEVERITY_COLORS.critical,
            label: 'Déconnectée',
            hasConnectorMismatch,
        };
    }

    if (station.operational_status === 'fault') {
        return {
            severity: 'critical',
            color: SEVERITY_COLORS.critical,
            label: 'Défaut',
            hasConnectorMismatch,
        };
    }

    if (station.operational_status === 'maintenance' || station.operational_status === 'out_of_service') {
        return {
            severity: 'warning',
            color: SEVERITY_COLORS.warning,
            label: station.operational_status === 'maintenance' ? 'Maintenance' : 'Hors service',
            hasConnectorMismatch,
        };
    }

    if (station.operational_status === 'occupied') {
        return {
            severity: 'info',
            color: SEVERITY_COLORS.info,
            label: 'Occupée',
            hasConnectorMismatch,
        };
    }

    return {
        severity: 'ok',
        color: SEVERITY_COLORS.ok,
        label: 'Disponible',
        hasConnectorMismatch,
    };
}

/**
 * True when a station has no usable coordinates and must be omitted from
 * the map (it still appears in the station list per the roadmap's rule).
 */
export function isMissingCoordinates(station: SupervisionStation): boolean {
    return (
        station.latitude === null ||
        station.longitude === null ||
        station.latitude === undefined ||
        station.longitude === undefined ||
        Number.isNaN(Number(station.latitude)) ||
        Number.isNaN(Number(station.longitude))
    );
}
