import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Table, Button, Space, Card, Row, Col, Typography, Input, Select } from 'antd';
import { PlusOutlined, ReloadOutlined, SearchOutlined } from '@ant-design/icons';
import { useSitesList, type Site } from '../api/sites';
import { useOrganizations } from '../api/stations';
import { usePermission } from '../auth/AuthContext';
import { useDebouncedValue } from '../utils/useDebouncedValue';
import AppShell from '../components/AppShell';

const { Title } = Typography;

export default function SiteListPage() {
  const navigate = useNavigate();
  const { data, isLoading, refetch } = useSitesList();
  const { data: organizations } = useOrganizations();
  const canCreate = usePermission('sites.create');
  const canUpdate = usePermission('sites.update');
  const canViewOrgs = usePermission('organizations.view');
  const [searchInput, setSearchInput] = useState('');
  const [orgFilter, setOrgFilter] = useState<number | undefined>(undefined);
  const debouncedSearch = useDebouncedValue(searchInput, 300);

  const filtered = (data ?? []).filter((s) => {
    if (orgFilter && s.organization_id !== orgFilter) return false;
    if (debouncedSearch) {
      const q = debouncedSearch.toLowerCase();
      return s.name.toLowerCase().includes(q) || s.address.toLowerCase().includes(q);
    }
    return true;
  });

  const orgLookup = Object.fromEntries((organizations ?? []).map((o) => [o.id, o.name]));

  const columns = [
    {
      title: 'Nom',
      dataIndex: 'name',
      key: 'name',
      render: (name: string, record: Site) =>
        canUpdate
          ? <a onClick={() => navigate(`/sites/${record.id}`)}>{name}</a>
          : name,
    },
    {
      title: 'Organisation',
      key: 'organization',
      render: (_: unknown, record: Site) => orgLookup[record.organization_id] ?? `Org #${record.organization_id}`,
    },
    { title: 'Adresse', dataIndex: 'address', key: 'address' },
    {
      title: 'Coordonnées',
      key: 'coords',
      render: (_: unknown, record: Site) =>
        record.latitude && record.longitude ? `${record.latitude}, ${record.longitude}` : '—',
    },
  ];

  return (
    <AppShell>
      <main className="station-page station-page--list">
        <Row className="station-page-header" justify="space-between" align="middle" gutter={[20, 16]}>
          <Col>
            <Title className="station-page-title" level={3}>Sites</Title>
          </Col>
          <Col>
            <Space className="station-page-actions" wrap>
              <Button icon={<ReloadOutlined />} onClick={() => refetch()}>Actualiser</Button>
              {canCreate && (
                <Button type="primary" icon={<PlusOutlined />} onClick={() => navigate('/sites/new')}>
                  Nouveau site
                </Button>
              )}
            </Space>
          </Col>
        </Row>

        <Card className="station-filter-card" size="small">
          <Row gutter={[12, 12]}>
            <Col xs={24} sm={12} md={12}>
              <Input
                className="station-filter-control"
                placeholder="Rechercher par nom ou adresse..."
                prefix={<SearchOutlined />}
                allowClear
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
              />
            </Col>
            {canViewOrgs && (
              <Col xs={24} sm={12} md={6}>
                <Select
                  className="station-filter-control"
                  placeholder="Organisation"
                  allowClear
                  value={orgFilter}
                  onChange={(v) => setOrgFilter(v)}
                  options={organizations?.map((o) => ({ value: o.id, label: o.name })) ?? []}
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
            dataSource={filtered}
            loading={isLoading}
            pagination={{
              pageSize: 15,
              showTotal: (total) => `${total} site(s)`,
              showSizeChanger: true,
              pageSizeOptions: [15, 30, 50, 100],
            }}
          />
        </Card>
      </main>
    </AppShell>
  );
}
