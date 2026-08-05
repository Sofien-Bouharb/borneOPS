import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Table, Button, Space, Card, Row, Col, Typography, Input, Tag } from 'antd';
import { PlusOutlined, ReloadOutlined, SearchOutlined } from '@ant-design/icons';
import { useOrganizationsList, type Organization } from '../api/organizations';
import { usePermission } from '../auth/AuthContext';
import { useDebouncedValue } from '../utils/useDebouncedValue';
import AppShell from '../components/AppShell';

const { Title } = Typography;

export default function OrganizationListPage() {
  const navigate = useNavigate();
  const { data, isLoading, refetch } = useOrganizationsList();
  const canCreate = usePermission('organizations.create');
  const canUpdate = usePermission('organizations.update');
  const [searchInput, setSearchInput] = useState('');
  const debouncedSearch = useDebouncedValue(searchInput, 300);

  const filtered = (data ?? []).filter((o) =>
    !debouncedSearch ||
    o.name.toLowerCase().includes(debouncedSearch.toLowerCase()) ||
    (o.contact_email?.toLowerCase().includes(debouncedSearch.toLowerCase()) ?? false),
  );

  const columns = [
    {
      title: 'Nom',
      dataIndex: 'name',
      key: 'name',
      render: (name: string, record: Organization) =>
        canUpdate
          ? <a onClick={() => navigate(`/organizations/${record.id}`)}>{name}</a>
          : name,
    },
    {
      title: 'Type',
      dataIndex: 'type',
      key: 'type',
      render: (type: string) => (
        <Tag color={type === 'operator' ? 'blue' : 'green'}>
          {type === 'operator' ? 'Opérateur' : 'Client'}
        </Tag>
      ),
    },
    { title: 'Email', dataIndex: 'contact_email', key: 'contact_email', render: (v: string | null) => v ?? '—' },
    { title: 'Téléphone', dataIndex: 'contact_phone', key: 'contact_phone', render: (v: string | null) => v ?? '—' },
    { title: 'Adresse', dataIndex: 'address', key: 'address', render: (v: string | null) => v ?? '—' },
  ];

  return (
    <AppShell>
      <main className="station-page station-page--list">
        <Row className="station-page-header" justify="space-between" align="middle" gutter={[20, 16]}>
          <Col>
            <Title className="station-page-title" level={3}>Organisations</Title>
          </Col>
          <Col>
            <Space className="station-page-actions" wrap>
              <Button icon={<ReloadOutlined />} onClick={() => refetch()}>Actualiser</Button>
              {canCreate && (
                <Button type="primary" icon={<PlusOutlined />} onClick={() => navigate('/organizations/new')}>
                  Nouvelle organisation
                </Button>
              )}
            </Space>
          </Col>
        </Row>

        <Card className="station-filter-card" size="small">
          <Input
            className="station-filter-control"
            placeholder="Rechercher par nom ou email..."
            prefix={<SearchOutlined />}
            allowClear
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
          />
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
              showTotal: (total) => `${total} organisation(s)`,
              showSizeChanger: true,
              pageSizeOptions: [15, 30, 50, 100],
            }}
          />
        </Card>
      </main>
    </AppShell>
  );
}
