// resources/js/components/StationMap.tsx
import { useMemo, useRef } from 'react';
import { MapContainer, TileLayer, CircleMarker, Popup } from 'react-leaflet';
import type {
    Map as LeafletMap,
    LatLngBoundsExpression,
    LeafletMouseEvent,
    PopupEvent,
    Layer,
} from 'leaflet';
import { useNavigate } from 'react-router-dom';
import { Tag, Typography, Button } from 'antd';
import { WarningOutlined, ArrowRightOutlined } from '@ant-design/icons';
import { SupervisionStation } from '../api/supervision';
import { getStationMarkerState, isMissingCoordinates } from '../utils/stationMarkerState';
import { usePermission } from '../auth/AuthContext';
import {
    ADMIN_STATUS_LABELS,
    OP_STATUS_LABELS,
    CONNECTION_STATUS_LABELS,
} from '../utils/stationLabels';

const { Text } = Typography;

const POPUP_CLOSE_DELAY_MS = 200;

// Same convention as StationListPage/StationDetailPage: administrative
// and operational status colors must stay recognizable (green = healthy,
// red = problem, orange = caution) regardless of the app's overall theme.
const ADMIN_STATUS_COLORS: Record<string, string> = {
    commissioning: 'processing',
    active: 'success',
    disabled: 'warning',
    decommissioned: 'default',
};

const OP_STATUS_COLORS: Record<string, string> = {
    available: 'success',
    occupied: 'processing',
    out_of_service: 'error',
    maintenance: 'warning',
    disconnected: 'default',
    fault: 'error',
};

function formatHeartbeat(value: string | null): string {
    if (!value) return 'Jamais';
    return new Date(value).toLocaleString();
}

interface StationMapProps {
    stations: SupervisionStation[];
    height?: number;
}

export default function StationMap({ stations, height = 480 }: StationMapProps) {
    const mapRef = useRef<LeafletMap | null>(null);
    const navigate = useNavigate();
    const canViewStationDetail = usePermission('charging_stations.view');
    const closeTimeoutsRef = useRef<Map<number, ReturnType<typeof setTimeout>>>(new Map());

    const plottableStations = useMemo(
        () => stations.filter((station) => !isMissingCoordinates(station)),
        [stations],
    );

    const bounds: LatLngBoundsExpression | null = useMemo(() => {
        if (plottableStations.length === 0) return null;
        return plottableStations.map((station) => [
            Number(station.latitude),
            Number(station.longitude),
        ]) as LatLngBoundsExpression;
    }, [plottableStations]);

    function cancelClose(stationId: number) {
        const timeout = closeTimeoutsRef.current.get(stationId);
        if (timeout) {
            clearTimeout(timeout);
            closeTimeoutsRef.current.delete(stationId);
        }
    }

    function scheduleClose(marker: Layer, stationId: number) {
        cancelClose(stationId);
        const timeout = setTimeout(() => {
            (marker as unknown as { closePopup: () => void }).closePopup();
            closeTimeoutsRef.current.delete(stationId);
        }, POPUP_CLOSE_DELAY_MS);
        closeTimeoutsRef.current.set(stationId, timeout);
    }

    if (plottableStations.length === 0) {
        return (
            <div
                style={{
                    height,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    color: '#5B6F7F',
                }}
            >
                Aucune borne géolocalisée à afficher.
            </div>
        );
    }

    return (
        <MapContainer
            bounds={bounds ?? undefined}
            style={{ height, width: '100%' }}
            ref={mapRef}
        >
            <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributeurs'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            {plottableStations.map((station) => {
                const markerState = getStationMarkerState(station);

                return (
                    <CircleMarker
                        key={station.id}
                        center={[Number(station.latitude), Number(station.longitude)]}
                        radius={9}
                        pathOptions={{
                            color: markerState.color,
                            fillColor: markerState.color,
                            fillOpacity: 0.85,
                            weight: 2,
                        }}
                        eventHandlers={{
                            mouseover: (e: LeafletMouseEvent) => {
                                cancelClose(station.id);
                                (e.target as unknown as { openPopup: () => void }).openPopup();
                            },
                            mouseout: (e: LeafletMouseEvent) => {
                                scheduleClose(e.target as Layer, station.id);
                            },
                            popupopen: (e: PopupEvent) => {
                                const popupEl = e.popup.getElement();
                                if (popupEl && !popupEl.dataset.hoverBound) {
                                    popupEl.dataset.hoverBound = 'true';
                                    popupEl.addEventListener('mouseenter', () => cancelClose(station.id));
                                    popupEl.addEventListener('mouseleave', () =>
                                        scheduleClose(e.target as Layer, station.id),
                                    );
                                }
                            },
                            ...(canViewStationDetail
                                ? { dblclick: () => navigate(`/stations/${station.id}`) }
                                : {}),
                        }}
                    >
                        <Popup autoPan={false} closeButton={false}>
                            <div style={{ minWidth: 200 }}>
                                <div style={{ fontWeight: 600, marginBottom: 2 }}>{station.name}</div>
                                <Text type="secondary" style={{ fontSize: 12 }}>
                                    {station.reference}
                                </Text>
                                <div style={{ margin: '8px 0 4px' }}>
                                    {station.site?.name ?? '—'}
                                    {station.site?.organization?.name
                                        ? ` · ${station.site.organization.name}`
                                        : ''}
                                </div>
                                <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap', marginBottom: 6 }}>
                                    <Tag color={ADMIN_STATUS_COLORS[station.administrative_status] ?? 'default'}>
                                        {ADMIN_STATUS_LABELS[station.administrative_status] ?? station.administrative_status}
                                    </Tag>
                                    <Tag color={OP_STATUS_COLORS[station.operational_status] ?? 'default'}>
                                        {OP_STATUS_LABELS[station.operational_status] ?? station.operational_status}
                                    </Tag>
                                    <Tag color={station.connection_status === 'connected' ? 'green' : 'red'}>
                                        {CONNECTION_STATUS_LABELS[station.connection_status] ?? station.connection_status}
                                    </Tag>
                                </div>
                                <div style={{ fontSize: 12, color: '#5B6F7F' }}>
                                    Dernier heartbeat : {formatHeartbeat(station.last_heartbeat_at)}
                                </div>
                                {station.connector_count_matches === false && (
                                    <div style={{ marginTop: 6, color: '#d4380d', fontSize: 12 }}>
                                        <WarningOutlined style={{ marginRight: 4 }} />
                                        {station.actual_connector_count}/{station.declared_connector_count} connecteurs
                                    </div>
                                )}
                                {canViewStationDetail && (
                                    <Button
                                        type="link"
                                        size="small"
                                        style={{ padding: 0, marginTop: 10 }}
                                        icon={<ArrowRightOutlined />}
                                        onClick={() => navigate(`/stations/${station.id}`)}
                                    >
                                        Voir les détails
                                    </Button>
                                )}
                            </div>
                        </Popup>
                    </CircleMarker>
                );
            })}
        </MapContainer>
    );
}
