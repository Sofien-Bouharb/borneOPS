import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Table, Tag, Input, Select, Button, Space, Card, Row, Col, Typography } from 'antd';
import { PlusOutlined, ReloadOutlined, SearchOutlined } from '@ant-design/icons';
import { useStations, useOrganizations, useSites, type StationFilters, type ChargingStation } from '../api/stations';

const { Title } = Typography;

const ADMIN_STATUS_COLORS: Record<string, string> = {
  commissioning: 'blue',
  active: 'green',
  disabled: 'orange',
  decommissioned: 'default',
};

const OP_STATUS_COLORS: Record<string, string> = {
  available: 'green',
  occupied: 'blue',
  out_of_service: 'red',
  maintenance: 'orange',
  disconnected: 'default',
  fault: 'red',
};

export default function StationListPage() {
  const navigate = useNavigate();
  const [filters, setFilters] = useState<StationFilters>({ page: 1 });
  const { data, isLoading, refetch } = useStations(filters);
  const { data: organizations } = useOrganizations();
  const { data: sites } = useSites();

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
        <Tag color={ADMIN_STATUS_COLORS[status] ?? 'default'}>{status}</Tag>
      ),
    },
    {
      title: 'État opérationnel',
      dataIndex: 'operational_status',
      key: 'operational_status',
      render: (status: string) => (
        <Tag color={OP_STATUS_COLORS[status] ?? 'default'}>{status}</Tag>
      ),
    },
  ];

  return (
    <div style={{ padding: '24px' }}>
      <Row justify="space-between" align="middle" style={{ marginBottom: 16 }}>
        <Col>
          <Title level={3} style={{ margin: 0 }}>Bornes de recharge</Title>
        </Col>
        <Col>
          <Space>
            <Button icon={<ReloadOutlined />} onClick={() => refetch()}>
              Actualiser
            </Button>
            <Button type="primary" icon={<PlusOutlined />} onClick={() => navigate('/stations/new')}>
              Nouvelle borne
            </Button>
          </Space>
        </Col>
      </Row>

      <Card size="small" style={{ marginBottom: 16 }}>
        <Row gutter={[12, 12]}>
          <Col xs={24} sm={12} md={6}>
            <Input
              placeholder="Rechercher..."
              prefix={<SearchOutlined />}
              allowClear
              onChange={(e) => updateFilter('search', e.target.value)}
            />
          </Col>
          <Col xs={24} sm={12} md={5}>
            <Select
              placeholder="Statut admin."
              allowClear
              style={{ width: '100%' }}
              onChange={(v) => updateFilter('administrative_status', v)}
              options={[
                { value: 'commissioning', label: 'Commissioning' },
                { value: 'active', label: 'Active' },
                { value: 'disabled', label: 'Disabled' },
                { value: 'decommissioned', label: 'Decommissioned' },
              ]}
            />
          </Col>
          <Col xs={24} sm={12} md={5}>
            <Select
              placeholder="État opérationnel"
              allowClear
              style={{ width: '100%' }}
              onChange={(v) => updateFilter('operational_status', v)}
              options={[
                { value: 'available', label: 'Available' },
                { value: 'occupied', label: 'Occupied' },
                { value: 'out_of_service', label: 'Out of service' },
                { value: 'maintenance', label: 'Maintenance' },
                { value: 'disconnected', label: 'Disconnected' },
                { value: 'fault', label: 'Fault' },
              ]}
            />
          </Col>
          <Col xs={24} sm={12} md={4}>
            <Select
              placeholder="Organisation"
              allowClear
              style={{ width: '100%' }}
              onChange={(v) => updateFilter('organization_id', v)}
              options={organizations?.map((o) => ({ value: o.id, label: o.name })) ?? []}
            />
          </Col>
          <Col xs={24} sm={12} md={4}>
            <Select
              placeholder="Site"
              allowClear
              style={{ width: '100%' }}
              onChange={(v) => updateFilter('site_id', v)}
              options={sites?.map((s) => ({ value: s.id, label: s.name })) ?? []}
            />
          </Col>
        </Row>
      </Card>

      <Table
        rowKey="id"
        columns={columns}
        dataSource={data?.data ?? []}
        loading={isLoading}
        pagination={{
          current: data?.meta.current_page ?? 1,
          pageSize: data?.meta.per_page ?? 15,
          total: data?.meta.total ?? 0,
          onChange: (page) => setFilters((prev) => ({ ...prev, page })),
          showTotal: (total) => `${total} borne(s)`,
          showSizeChanger: false,
        }}
        onRow={(record) => ({
          onClick: () => navigate(`/stations/${record.id}`),
          style: { cursor: 'pointer' },
        })}
      />
    </div>
  );
}
