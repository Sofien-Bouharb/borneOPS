import { useNavigate } from 'react-router-dom';
import { Table, Tag, Select, Button, Space, Card, Row, Col, Typography } from 'antd';
import { PlusOutlined, ReloadOutlined } from '@ant-design/icons';
import { useState } from 'react';
import { useChargingSessions, type ChargingSessionFilters, type ChargingSession } from '../api/chargingSessions';
import { usePermission } from '../auth/AuthContext';
import AppShell from '../components/AppShell';

const { Title } = Typography;

const STATUS_LABELS: Record<string, string> = {
  pending: 'En attente',
  active: 'En charge',
  paused: 'En pause',
  completed: 'Terminée',
  cancelled: 'Annulée',
};

const STATUS_COLORS: Record<string, string> = {
  pending: 'default',
  active: 'processing',
  paused: 'warning',
  completed: 'success',
  cancelled: 'error',
};

function formatDuration(seconds: number | null): string {
  if (seconds === null) return '—';
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  if (h > 0) return `${h}h ${m}min`;
  return `${m}min`;
}

export default function ChargingSessionListPage() {
  const navigate = useNavigate();
  const [filters, setFilters] = useState<ChargingSessionFilters>({ page: 1 });
  const { data, isLoading, refetch } = useChargingSessions(filters);
  const canCreate = usePermission('charging_sessions.create');

  const updateFilter = (key: keyof ChargingSessionFilters, value: unknown) => {
    setFilters((prev) => ({ ...prev, [key]: value || undefined, page: 1 }));
  };

  const columns = [
    {
      title: 'ID',
      dataIndex: 'id',
      key: 'id',
      render: (id: number) => (
        <a onClick={() => navigate(`/charging-sessions/${id}`)}>#{id}</a>
      ),
    },
    {
      title: 'Borne',
      key: 'station',
      render: (_: unknown, record: ChargingSession) =>
        record.charging_station?.name ?? <span style={{ color: 'var(--ops-text-muted)' }}>—</span>,
    },
    {
      title: 'Connecteur',
      key: 'connector',
      render: (_: unknown, record: ChargingSession) =>
        record.connector ? `#${record.connector.connector_number}` : <span style={{ color: 'var(--ops-text-muted)' }}>—</span>,
    },
    {
      title: 'Statut',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => (
        <Tag color={STATUS_COLORS[status] ?? 'default'}>{STATUS_LABELS[status] ?? status}</Tag>
      ),
    },
    {
      title: 'Énergie',
      key: 'energy',
      render: (_: unknown, record: ChargingSession) =>
        record.energy_consumed_kwh !== null ? `${record.energy_consumed_kwh} kWh` : '—',
    },
    {
      title: 'Durée',
      key: 'duration',
      render: (_: unknown, record: ChargingSession) => formatDuration(record.duration_seconds),
    },
    {
      title: 'Créée le',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (val: string) => new Date(val).toLocaleString('fr-FR'),
    },
  ];

  return (
    <AppShell>
      <main className="station-page station-page--list">
        <Row className="station-page-header" justify="space-between" align="middle" gutter={[20, 16]}>
          <Col>
            <Title className="station-page-title" level={3}>Sessions de recharge</Title>
          </Col>
          <Col>
            <Space className="station-page-actions" wrap>
              <Button icon={<ReloadOutlined />} onClick={() => refetch()}>
                Actualiser
              </Button>
              {canCreate && (
                <Button type="primary" icon={<PlusOutlined />} onClick={() => navigate('/charging-sessions/new')}>
                  Nouvelle session
                </Button>
              )}
            </Space>
          </Col>
        </Row>

        <Card className="station-filter-card" size="small">
          <Row gutter={[12, 12]}>
            <Col xs={24} sm={12} md={6}>
              <Select
                className="station-filter-control"
                placeholder="Statut"
                allowClear
                style={{ width: '100%' }}
                onChange={(v) => updateFilter('status', v)}
                options={Object.entries(STATUS_LABELS).map(([value, label]) => ({ value, label }))}
              />
            </Col>
          </Row>
        </Card>

        <Card className="station-table-card">
          <Table
            className="station-table"
            rowKey="id"
            columns={columns}
            dataSource={data?.data ?? []}
            loading={isLoading}
            scroll={{ x: 900 }}
            pagination={{
              current: data?.meta.current_page ?? 1,
              pageSize: data?.meta.per_page ?? 15,
              total: data?.meta.total ?? 0,
              onChange: (page, pageSize) => setFilters((prev) => ({ ...prev, page, per_page: pageSize })),
              showTotal: (total) => `${total} session(s)`,
              showSizeChanger: true,
              pageSizeOptions: [15, 30, 50, 100],
            }}
            onRow={(record) => ({
              onClick: () => navigate(`/charging-sessions/${record.id}`),
              className: 'station-table__row',
            })}
          />
        </Card>
      </main>
    </AppShell>
  );
}
