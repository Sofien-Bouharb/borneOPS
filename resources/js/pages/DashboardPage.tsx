// resources/js/pages/DashboardPage.tsx
import { useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { Card, Col, Row, Statistic, Table, Tag, Typography, Alert, Tooltip } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import {
    ThunderboltOutlined,
    WifiOutlined,
    DisconnectOutlined,
    ApiOutlined,
    WarningOutlined,
    EnvironmentOutlined,
} from '@ant-design/icons';
import echo from '../echo';
import AppShell from '../components/AppShell';
import StationMap from '../components/StationMap';
import { useSupervisionDashboard, SupervisionStation, SupervisionDashboard } from '../api/supervision';
import { usePermission } from '../auth/AuthContext';
import {
    ADMIN_STATUS_LABELS,
    OP_STATUS_LABELS,
    CONNECTION_STATUS_LABELS,
} from '../utils/stationLabels';

const { Text } = Typography;

function connectionStatusColor(status: string): string {
    return status === 'connected' ? 'green' : 'red';
}

function operationalStatusColor(status: string): string {
    const colors: Record<string, string> = {
        available: 'green',
        occupied: 'blue',
        out_of_service: 'default',
        maintenance: 'orange',
        disconnected: 'default',
        fault: 'red',
    };
    return colors[status] ?? 'default';
}

function formatHeartbeat(value: string | null): string {
    if (!value) return 'Jamais';
    const date = new Date(value);
    const diffSeconds = Math.round((Date.now() - date.getTime()) / 1000);
    if (diffSeconds < 60) return `Il y a ${diffSeconds}s`;
    if (diffSeconds < 3600) return `Il y a ${Math.round(diffSeconds / 60)} min`;
    return date.toLocaleString();
}

function heartbeatSortValue(value: string | null): number {
    return value ? new Date(value).getTime() : -1;
}

export default function DashboardPage() {
    const { data, isLoading, isError } = useSupervisionDashboard();
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const canViewStationDetail = usePermission('charging_stations.view');
    const refetchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        echo.private('supervision').listen(
            '.station.updated',
            (e: { station: SupervisionStation }) => {
                console.log('Received event:', e);

                queryClient.setQueryData<SupervisionDashboard | undefined>(
                    ['supervision-dashboard'],
                    (current) => {
                        if (!current) return current;

                        const stationExists = current.stations.some((s) => s.id === e.station.id);

                        const updatedStations = stationExists
                            ? current.stations.map((s) => (s.id === e.station.id ? e.station : s))
                            : [...current.stations, e.station];

                        return { ...current, stations: updatedStations };
                    },
                );

                if (refetchTimeoutRef.current) {
                    clearTimeout(refetchTimeoutRef.current);
                }
                refetchTimeoutRef.current = setTimeout(() => {
                    queryClient.invalidateQueries({ queryKey: ['supervision-dashboard'] });
                }, 500);
            },
        );

        console.log('Subscribed to private-supervision channel.');

        return () => {
            if (refetchTimeoutRef.current) {
                clearTimeout(refetchTimeoutRef.current);
            }
            echo.leave('supervision');
        };
    }, [queryClient]);

    const columns: ColumnsType<SupervisionStation> = [
        {
            title: 'Borne',
            dataIndex: 'name',
            key: 'name',
            sorter: (a, b) => a.name.localeCompare(b.name),
            render: (_, station) => (
                <div>
                    <div>{station.name}</div>
                    <Text type="secondary" style={{ fontSize: 12 }}>
                        {station.reference}
                    </Text>
                </div>
            ),
        },
        {
            title: 'Site / Organisation',
            key: 'site',
            render: (_, station) => (
                <div>
                    <div>{station.site?.name ?? '—'}</div>
                    <Text type="secondary" style={{ fontSize: 12 }}>
                        {station.site?.organization?.name ?? ''}
                    </Text>
                </div>
            ),
        },
        {
            title: 'Statut administratif',
            dataIndex: 'administrative_status',
            key: 'administrative_status',
            sorter: (a, b) => a.administrative_status.localeCompare(b.administrative_status),
            render: (value: string) => <Tag>{ADMIN_STATUS_LABELS[value] ?? value}</Tag>,
        },
        {
            title: 'État opérationnel',
            dataIndex: 'operational_status',
            key: 'operational_status',
            sorter: (a, b) => a.operational_status.localeCompare(b.operational_status),
            render: (value: string) => (
                <Tag color={operationalStatusColor(value)}>{OP_STATUS_LABELS[value] ?? value}</Tag>
            ),
        },
        {
            title: 'Connexion',
            dataIndex: 'connection_status',
            key: 'connection_status',
            sorter: (a, b) => a.connection_status.localeCompare(b.connection_status),
            render: (value: string) => (
                <Tag color={connectionStatusColor(value)}>
                    {CONNECTION_STATUS_LABELS[value] ?? value}
                </Tag>
            ),
        },
        {
            title: 'Dernier heartbeat',
            dataIndex: 'last_heartbeat_at',
            key: 'last_heartbeat_at',
            sorter: (a, b) => heartbeatSortValue(a.last_heartbeat_at) - heartbeatSortValue(b.last_heartbeat_at),
            render: (value: string | null) => formatHeartbeat(value),
        },
        {
            title: 'Connecteurs',
            key: 'connectors',
            render: (_, station) => {
                const mismatch = station.connector_count_matches === false;
                return (
                    <span>
                        {station.actual_connector_count ?? '—'}/{station.declared_connector_count}
                        {mismatch && (
                            <Tooltip title="Le nombre réel de connecteurs ne correspond pas au nombre déclaré.">
                                <WarningOutlined style={{ color: '#d4380d', marginLeft: 6 }} />
                            </Tooltip>
                        )}
                    </span>
                );
            },
        },
    ];

    const missingCoordinatesCount = data?.kpis.stations_missing_coordinates ?? 0;

    return (
        <AppShell>
            <div style={{ padding: 24 }}>
                <div className="dashboard-heading">
                    <div>
                        <p className="section-eyebrow">Real-time supervision</p>
                        <h1>Supervision dashboard</h1>
                        <p>Live overview of every charging station across the network.</p>
                    </div>
                </div>

                {isError && (
                    <Alert
                        type="error"
                        message="Impossible de charger le tableau de bord de supervision."
                        style={{ marginBottom: 24 }}
                    />
                )}

                <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
                    <Col xs={12} sm={8} md={4}>
                        <Card loading={isLoading}>
                            <Statistic
                                title="Bornes"
                                value={data?.kpis.stations_total ?? 0}
                                prefix={<ThunderboltOutlined />}
                            />
                        </Card>
                    </Col>
                    <Col xs={12} sm={8} md={4}>
                        <Card loading={isLoading}>
                            <Statistic
                                title="Connectées"
                                value={data?.kpis.stations_by_connection_status.connected ?? 0}
                                prefix={<WifiOutlined />}
                                styles={{ content: { color: '#157A6E' } }}
                            />
                        </Card>
                    </Col>
                    <Col xs={12} sm={8} md={4}>
                        <Card loading={isLoading}>
                            <Statistic
                                title="Déconnectées"
                                value={data?.kpis.stations_by_connection_status.disconnected ?? 0}
                                prefix={<DisconnectOutlined />}
                                styles={{ content: { color: '#a8071a' } }}
                            />
                        </Card>
                    </Col>
                    <Col xs={12} sm={8} md={4}>
                        <Card loading={isLoading}>
                            <Statistic
                                title="Connecteurs"
                                value={data?.kpis.connectors_total ?? 0}
                                prefix={<ApiOutlined />}
                            />
                        </Card>
                    </Col>
                    <Col xs={12} sm={8} md={4}>
                        <Card loading={isLoading}>
                            <Statistic
                                title="Écarts connecteurs"
                                value={data?.kpis.stations_with_connector_mismatch ?? 0}
                                prefix={<WarningOutlined />}
                                styles={{
                                    content: {
                                        color: (data?.kpis.stations_with_connector_mismatch ?? 0) > 0 ? '#d4380d' : undefined,
                                    },
                                }}
                            />
                        </Card>
                    </Col>
                    {missingCoordinatesCount > 0 && (
                        <Col xs={12} sm={8} md={4}>
                            <Card loading={isLoading}>
                                <Statistic
                                    title="Coordonnées manquantes"
                                    value={missingCoordinatesCount}
                                    prefix={<EnvironmentOutlined />}
                                    styles={{ content: { color: '#d4380d' } }}
                                />
                            </Card>
                        </Col>
                    )}
                </Row>

                <Card
                    title="Carte en temps réel"
                    style={{ marginBottom: 24 }}
                    styles={{ body: { padding: 0 } }}
                    loading={isLoading}
                >
                    <StationMap stations={data?.stations ?? []} />
                    {missingCoordinatesCount > 0 && (
                        <div style={{ padding: '10px 16px', borderTop: '1px solid #E7EDF1', fontSize: 13, color: '#5B6F7F' }}>
                            <WarningOutlined style={{ marginRight: 6, color: '#d4380d' }} />
                            {missingCoordinatesCount} borne{missingCoordinatesCount > 1 ? 's' : ''} sans coordonnées
                            {missingCoordinatesCount > 1 ? ' ne sont' : ' n\'est'} pas affichée
                            {missingCoordinatesCount > 1 ? 's' : ''} sur la carte (toujours visible
                            {missingCoordinatesCount > 1 ? 's' : ''} dans la liste ci-dessous).
                        </div>
                    )}
                </Card>

                <Card title="Bornes" styles={{ body: { padding: 0 } }}>
                    <Table
                        rowKey="id"
                        columns={columns}
                        dataSource={data?.stations ?? []}
                        loading={isLoading}
                        pagination={{ pageSize: 15 }}
                        scroll={{ x: 1000 }}
                        onRow={(station) =>
                            canViewStationDetail
                                ? {
                                      onClick: () => navigate(`/stations/${station.id}`),
                                      style: { cursor: 'pointer' },
                                  }
                                : {}
                        }
                    />
                </Card>
            </div>
        </AppShell>
    );
}
