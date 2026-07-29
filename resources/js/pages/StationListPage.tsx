import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Table, Tag, Input, Select, Button, Space, Card, Row, Col, Typography } from 'antd';
import { PlusOutlined, ReloadOutlined, SearchOutlined } from '@ant-design/icons';
import { useStations, useOrganizations, useSites, type StationFilters, type ChargingStation } from '../api/stations';
import '../../css/stations.css';
import { usePermission } from '../auth/AuthContext';
import { ADMIN_STATUS_LABELS, OP_STATUS_LABELS } from '../utils/stationLabels';
import { useDebouncedValue } from '../utils/useDebouncedValue';

const { Title } = Typography;

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

export default function StationListPage() {
  const navigate = useNavigate();
  const [filters, setFilters] = useState<StationFilters>({ page: 1 });
  const [searchInput, setSearchInput] = useState('');
  const debouncedSearch = useDebouncedValue(searchInput, 300);
  const { data, isLoading, refetch } = useStations({ ...filters, search: debouncedSearch || undefined });
  const { data: organizations } = useOrganizations();
  const { data: sites } = useSites();
  const canCreate = usePermission('charging_stations.create');
  const canViewOrgs = usePermission('organizations.view');
  const canViewSites = usePermission('sites.view');
  const updateFilter = (key: keyof StationFilters, value: unknown) => {
    setFilters((prev) => ({ ...prev, [key]: value || undefined, page: 1 }));
  };

  const columns = [
    {
      title: 'Nom',
      dataIndex: 'name',
      key: 'name',
      render: (name: string, record: ChargingStation) => (
        <a onClick={() => navigate(`/stations/${record.id}`)}>{name}</a>
      ),
    },
    {
      title: 'Référence',
      dataIndex: 'reference',
      key: 'reference',
    },
    {
      title: 'Fabricant',
      dataIndex: 'manufacturer',
      key: 'manufacturer',
    },
    {
      title: 'Site',
      key: 'site',
      render: (_: unknown, record: ChargingStation) =>
        record.site?.name ?? <span style={{ color: '#999' }}>—</span>,
    },
    {
      title: 'Organisation',
      key: 'organization',
      render: (_: unknown, record: ChargingStation) =>
        record.site?.organization?.name ?? <span style={{ color: '#999' }}>—</span>,
    },
    {
      title: 'Puissance',
      dataIndex: 'power_kw',
      key: 'power_kw',
      render: (val: string) => `${val} kW`,
    },
    {
      title: 'Statut admin.',
      dataIndex: 'administrative_status',
      key: 'administrative_status',
      render: (status: string) => (
        <Tag className="station-status-tag" color={ADMIN_STATUS_COLORS[status] ?? 'default'}>
          {ADMIN_STATUS_LABELS[status] ?? status}
        </Tag>
      ),
    },
    {
      title: 'État opérationnel',
      dataIndex: 'operational_status',
      key: 'operational_status',
      render: (status: string) => (
        <Tag className="station-status-tag" color={OP_STATUS_COLORS[status] ?? 'default'}>
          {OP_STATUS_LABELS[status] ?? status}
        </Tag>
      ),
    },
  ];

  return (
    <main className="station-page station-page--list">
      <Row className="station-page-header" justify="space-between" align="middle" gutter={[20, 16]}>
        <Col>
          <Title className="station-page-title" level={3}>Bornes de recharge</Title>
        </Col>
        <Col>
          <Space className="station-page-actions" wrap>
            <Button icon={<ReloadOutlined />} onClick={() => refetch()}>
              Actualiser
            </Button>
            {canCreate && (
              <Button type="primary" icon={<PlusOutlined />} onClick={() => navigate('/stations/new')}>
                Nouvelle borne
              </Button>
            )}
          </Space>
        </Col>
      </Row>

      <Card className="station-filter-card" size="small">
        <Row gutter={[12, 12]}>
          <Col xs={24} sm={12} md={6}>
            <Input
              className="station-filter-control"
              placeholder="Rechercher..."
              prefix={<SearchOutlined />}
              allowClear
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
            />
          </Col>
          <Col xs={24} sm={12} md={5}>
            <Select
              className="station-filter-control"
              placeholder="Statut admin."
              allowClear
              onChange={(v) => updateFilter('administrative_status', v)}
              options={Object.entries(ADMIN_STATUS_LABELS).map(([value, label]) => ({ value, label }))}
            />
          </Col>
          <Col xs={24} sm={12} md={5}>
            <Select
              className="station-filter-control"
              placeholder="État opérationnel"
              allowClear
              onChange={(v) => updateFilter('operational_status', v)}
              options={Object.entries(OP_STATUS_LABELS).map(([value, label]) => ({ value, label }))}
            />
          </Col>
          {canViewOrgs && (
          <Col xs={24} sm={12} md={4}>
            <Select
              className="station-filter-control"
              placeholder="Organisation"
              allowClear
              value={filters.organization_id}
              onChange={(v) => {
                setFilters((prev) => {
                  const next = { ...prev, organization_id: v || undefined, page: 1 };
                  // If a site was selected but no longer belongs to the new org, clear it
                  if (next.organization_id && next.site_id) {
                    const site = sites?.find((s) => s.id === next.site_id);
                    if (!site || site.organization_id !== next.organization_id) {
                      next.site_id = undefined;
                    }
                  }
                  return next;
                });
              }}
        options={organizations?.map((o) => ({ value: o.id, label: o.name })) ?? []}
            />
          </Col>
          )}
          {canViewSites && (
          <Col xs={24} sm={12} md={4}>
            <Select
              className="station-filter-control"
              placeholder="Site"
              allowClear
              value={filters.site_id}
              onChange={(v) => updateFilter('site_id', v)}
              options={
                sites
                  ?.filter((s) => !filters.organization_id || s.organization_id === filters.organization_id)
                  .map((s) => ({ value: s.id, label: s.name })) ?? []
              }
            />
          </Col>
          )}
        </Row>
      </Card>

      <Card className="station-table-card">
        <Table
          className="station-table"
          rowKey="id"
          columns={columns}
          dataSource={data?.data ?? []}
          loading={isLoading}
          scroll={{ x: 920 }}
          pagination={{
            current: data?.meta.current_page ?? 1,
            pageSize: data?.meta.per_page ?? 15,
            total: data?.meta.total ?? 0,
            onChange: (page, pageSize) => setFilters((prev) => ({ ...prev, page, per_page: pageSize })),
            showTotal: (total) => `${total} borne(s)`,
            showSizeChanger: true,
            pageSizeOptions: [15, 30, 50, 100],
          }}
          onRow={(record) => ({
            onClick: () => navigate(`/stations/${record.id}`),
            className: 'station-table__row',
          })}
        />
      </Card>
    </main>
  );
}
