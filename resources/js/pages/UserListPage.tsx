import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Table, Button, Space, Card, Row, Col, Typography, Input, Tag, Select, Popconfirm, message } from 'antd';
import { PlusOutlined, ReloadOutlined, SearchOutlined } from '@ant-design/icons';
import { useUsers, useUpdateAccountStatus, useResendPasswordSetupLink, ASSIGNABLE_ROLES, type AppUser } from '../api/users';
import { usePermission } from '../auth/AuthContext';
import { useDebouncedValue } from '../utils/useDebouncedValue';
import { extractErrorMessage } from '../utils/apiErrors';
import AppShell from '../components/AppShell';

const { Title } = Typography;

const ROLE_COLORS: Record<string, string> = {
  'Super Administrator': 'red',
  'Exploitant': 'volcano',
  'Opérateur': 'blue',
  'Technicien': 'geekblue',
  'Service Client': 'purple',
  'Finance': 'gold',
  'Client': 'green',
};

export default function UserListPage() {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const [searchInput, setSearchInput] = useState('');
  const [accountStatus, setAccountStatus] = useState<string | undefined>(undefined);
  const [role, setRole] = useState<string | undefined>(undefined);
  const debouncedSearch = useDebouncedValue(searchInput, 300);

  const { data, isLoading, refetch } = useUsers({
    page,
    search: debouncedSearch || undefined,
    account_status: accountStatus,
    role,
  });

  const canCreate = usePermission('users.create');
  const canDisable = usePermission('users.disable');

  const statusMutation = useUpdateAccountStatus();
  const resendMutation = useResendPasswordSetupLink();

  const toggleAccountStatus = async (user: AppUser) => {
    const next = user.account_status === 'active' ? 'disabled' : 'active';
    try {
      await statusMutation.mutateAsync({ id: user.id, account_status: next });
      message.success(next === 'active' ? 'Compte activé' : 'Compte désactivé');
    } catch (err) {
      message.error(extractErrorMessage(err));
    }
  };

  const handleResend = async (user: AppUser) => {
    try {
      await resendMutation.mutateAsync(user.id);
      message.success('Lien de configuration renvoyé');
    } catch (err) {
      message.error(extractErrorMessage(err));
    }
  };

  const columns = [
    {
      title: 'Nom',
      dataIndex: 'name',
      key: 'name',
      render: (name: string, record: AppUser) =>
        canDisable ? <a onClick={() => navigate(`/users/${record.id}`)}>{name}</a> : name,
    },
    { title: 'Email', dataIndex: 'email', key: 'email' },
    {
      title: 'Rôle(s)',
      dataIndex: 'roles',
      key: 'roles',
      render: (roles: string[]) =>
        roles.length === 0
          ? <Tag>Aucun rôle</Tag>
          : roles.map((r) => <Tag key={r} color={ROLE_COLORS[r] ?? 'default'}>{r}</Tag>),
    },
    {
      title: 'Organisation(s)',
      dataIndex: 'organizations',
      key: 'organizations',
      render: (orgs: AppUser['organizations']) =>
        orgs.length === 0 ? '—' : orgs.map((o) => o.name).join(', '),
    },
    {
      title: 'Statut',
      dataIndex: 'account_status',
      key: 'account_status',
      render: (status: string) => (
        <Tag color={status === 'active' ? 'green' : 'red'}>
          {status === 'active' ? 'Actif' : 'Désactivé'}
        </Tag>
      ),
    },
    {
      title: 'Dernière connexion',
      dataIndex: 'last_login_at',
      key: 'last_login_at',
      render: (v: string | null) => (v ? new Date(v).toLocaleString('fr-FR') : '—'),
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: unknown, record: AppUser) => (
        <Space>
          {canDisable && (
            <Popconfirm
              title={record.account_status === 'active' ? 'Désactiver ce compte ?' : 'Activer ce compte ?'}
              onConfirm={() => toggleAccountStatus(record)}
              okText="Confirmer"
              cancelText="Annuler"
            >
              <Button size="small" danger={record.account_status === 'active'}>
                {record.account_status === 'active' ? 'Désactiver' : 'Activer'}
              </Button>
            </Popconfirm>
          )}
          {canDisable && (
            <Button size="small" onClick={() => handleResend(record)} loading={resendMutation.isPending}>
              Renvoyer le lien
            </Button>
          )}
        </Space>
      ),
    },
  ];

  return (
    <AppShell>
      <main className="station-page station-page--list">
        <Row className="station-page-header" justify="space-between" align="middle" gutter={[20, 16]}>
          <Col>
            <Title className="station-page-title" level={3}>Utilisateurs</Title>
          </Col>
          <Col>
            <Space className="station-page-actions" wrap>
              <Button icon={<ReloadOutlined />} onClick={() => refetch()}>Actualiser</Button>
              {canCreate && (
                <Button type="primary" icon={<PlusOutlined />} onClick={() => navigate('/users/new')}>
                  Nouvel utilisateur
                </Button>
              )}
            </Space>
          </Col>
        </Row>

        <Card className="station-filter-card" size="small">
          <Space wrap>
            <Input
              className="station-filter-control"
              placeholder="Rechercher par nom ou email..."
              prefix={<SearchOutlined />}
              allowClear
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              style={{ width: 260 }}
            />
            <Select
              placeholder="Statut"
              allowClear
              value={accountStatus}
              onChange={setAccountStatus}
              style={{ width: 150 }}
              options={[
                { value: 'active', label: 'Actif' },
                { value: 'disabled', label: 'Désactivé' },
              ]}
            />
            <Select
              placeholder="Rôle"
              allowClear
              value={role}
              onChange={setRole}
              style={{ width: 180 }}
              options={ASSIGNABLE_ROLES.map((r) => ({ value: r, label: r }))}
            />
          </Space>
        </Card>

        <Card className="station-table-card">
          <Table
            className="station-table"
            rowKey="id"
            columns={columns}
            dataSource={data?.data ?? []}
            loading={isLoading}
            pagination={{
              current: page,
              pageSize: data?.meta.per_page ?? 15,
              total: data?.meta.total ?? 0,
              showTotal: (total) => `${total} utilisateur(s)`,
              onChange: (p) => setPage(p),
            }}
          />
        </Card>
      </main>
    </AppShell>
  );
}
