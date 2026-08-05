// resources/js/components/StationMap.tsx
import { useMemo, useRef } from 'react';
import { MapContainer, TileLayer, CircleMarker, Popup } from 'react-leaflet';
import type { Map as LeafletMap, LatLngBoundsExpression } from 'leaflet';
import { Tag, Typography } from 'antd';
import { WarningOutlined } from '@ant-design/icons';
import { SupervisionStation } from '../api/supervision';
import { getStationMarkerState, isMissingCoordinates } from '../utils/stationMarkerState';
import {
    ADMIN_STATUS_LABELS,
    OP_STATUS_LABELS,
    CONNECTION_STATUS_LABELS,
} from '../utils/stationLabels';

const { Text } = Typography;

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
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
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
                    >
                        <Popup>
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
                                    <Tag>{ADMIN_STATUS_LABELS[station.administrative_status] ?? station.administrative_status}</Tag>
                                    <Tag>{OP_STATUS_LABELS[station.operational_status] ?? station.operational_status}</Tag>
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
                            </div>
                        </Popup>
                    </CircleMarker>
                );
            })}
        </MapContainer>
    );
}
