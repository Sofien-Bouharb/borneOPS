import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Table, Button, Space, Card, Typography, Tag, Select, Popconfirm, message, Modal, Form, Input, DatePicker, Drawer, List } from 'antd';
import { PlusOutlined, ReloadOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import {
  useRfidBadges,
  useCreateRfidBadge,
  useActivateRfidBadge,
  useBlockRfidBadge,
  useReassignRfidBadge,
  useUpdateRfidBadgeExpiration,
  useRfidBadgeHistory,
  type RfidBadge,
} from '../api/rfid-badges';
import { useUsers } from '../api/users';
import { usePermission } from '../auth/AuthContext';
import { extractErrorMessage } from '../utils/apiErrors';
import AppShell from '../components/AppShell';

const { Title } = Typography;

const STATUS_COLORS: Record<string, string> = {
  pending: 'default',
  active: 'green',
  blocked: 'red',
};

export default function RfidBadgeListPage() {
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<string | undefined>(undefined);
  const [createOpen, setCreateOpen] = useState(false);
  const [reassignBadge, setReassignBadge] = useState<RfidBadge | null>(null);
  const [expirationBadge, setExpirationBadge] = useState<RfidBadge | null>(null);
  const [historyBadge, setHistoryBadge] = useState<RfidBadge | null>(null);
  const [createForm] = Form.useForm();
  const [reassignForm] = Form.useForm();
  const [expirationForm] = Form.useForm();

  const { data, isLoading, refetch } = useRfidBadges({ page, administrative_status: status });
  const { data: clientsData } = useUsers({ role: 'Client', per_page: 100 } as any);

  const canCreate = usePermission('rfid_badges.create');
  const canActivate = usePermission('rfid_badges.activate');
  const canBlock = usePermission('rfid_badges.block');
  const canReassign = usePermission('rfid_badges.reassign');
  const canUpdateExpiration = usePermission('rfid_badges.expiration.update');
  const canViewHistory = usePermission('rfid_badges.history.view');

  const createMutation = useCreateRfidBadge();
  const activateMutation = useActivateRfidBadge();
  const blockMutation = useBlockRfidBadge();
  const reassignMutation = useReassignRfidBadge();
  const expirationMutation = useUpdateRfidBadgeExpiration();
  const { data: historyData } = useRfidBadgeHistory(historyBadge?.id ?? 0);

  const handleCreate = async () => {
    try {
      const values = await createForm.validateFields();
      await createMutation.mutateAsync({
        user_id: values.user_id,
        identifier: values.identifier,
        label: values.label || undefined,
        expires_at: values.expires_at ? values.expires_at.toISOString() : undefined,
      });
      message.success('Badge créé');
      setCreateOpen(false);
      createForm.resetFields();
    } catch (err: any) {
      if (err?.errorFields) return;
      message.error(extractErrorMessage(err));
    }
  };

  const handleActivate = async (badge: RfidBadge) => {
    try {
      await activateMutation.mutateAsync(badge.id);
      message.success('Badge activé');
    } catch (err) {
      message.error(extractErrorMessage(err));
    }
  };

  const handleBlock = async (badge: RfidBadge) => {
    try {
      await blockMutation.mutateAsync(badge.id);
      message.success('Badge bloqué');
    } catch (err) {
      message.error(extractErrorMessage(err));
    }
  };

  const handleReassign = async () => {
    if (!reassignBadge) return;
    try {
      const values = await reassignForm.validateFields();
      await reassignMutation.mutateAsync({ id: reassignBadge.id, user_id: values.user_id });
      message.success('Badge réattribué');
      setReassignBadge(null);
      reassignForm.resetFields();
    } catch (err: any) {
      if (err?.errorFields) return;
      message.error(extractErrorMessage(err));
    }
  };

  const handleExpiration = async () => {
    if (!expirationBadge) return;
    try {
      const values = await expirationForm.validateFields();
      await expirationMutation.mutateAsync({
        id: expirationBadge.id,
        expires_at: values.expires_at ? values.expires_at.toISOString() : null,
      });
      message.success('Expiration mise à jour');
      setExpirationBadge(null);
      expirationForm.resetFields();
    } catch (err: any) {
      if (err?.errorFields) return;
      message.error(extractErrorMessage(err));
    }
  };

  const columns = [
    { title: 'Indice', dataIndex: 'identifier_hint', key: 'identifier_hint', render: (v: string) => <code>{v}</code> },
    { title: 'Libellé', dataIndex: 'label', key: 'label', render: (v: string | null) => v || '—' },
    {
      title: 'Propriétaire',
      key: 'user',
      render: (_: unknown, r: RfidBadge) => r.user ? `${r.user.name} (${r.user.email})` : '—',
    },
    {
      title: 'Statut',
      dataIndex: 'administrative_status',
      key: 'administrative_status',
      render: (v: string) => <Tag color={STATUS_COLORS[v]}>{v}</Tag>,
    },
    {
      title: 'Expiration',
      dataIndex: 'expires_at',
      key: 'expires_at',
      render: (v: string | null) => v ? dayjs(v).format('DD/MM/YYYY HH:mm') : '—',
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: unknown, r: RfidBadge) => (
        <Space wrap>
          {canActivate && r.administrative_status !== 'active' && (
            <Popconfirm title="Activer ce badge ?" onConfirm={() => handleActivate(r)}>
              <Button size="small">Activer</Button>
            </Popconfirm>
          )}
          {canBlock && r.administrative_status !== 'blocked' && (
            <Popconfirm title="Bloquer ce badge ?" onConfirm={() => handleBlock(r)}>
              <Button size="small" danger>Bloquer</Button>
            </Popconfirm>
          )}
          {canReassign && r.administrative_status !== 'active' && (
            <Button size="small" onClick={() => setReassignBadge(r)}>Réattribuer</Button>
          )}
          {canUpdateExpiration && (
            <Button size="small" onClick={() => { setExpirationBadge(r); expirationForm.setFieldsValue({ expires_at: r.expires_at ? dayjs(r.expires_at) : null }); }}>
              Expiration
            </Button>
          )}
          {canViewHistory && (
            <Button size="small" onClick={() => setHistoryBadge(r)}>Historique</Button>
          )}
        </Space>
      ),
    },
  ];

  return (
    <AppShell>
      <Card>
        <Space style={{ width: '100%', justifyContent: 'space-between', marginBottom: 16 }}>
          <Title level={3} style={{ margin: 0 }}>Badges RFID</Title>
          <Space>
            <Select
              placeholder="Statut"
              allowClear
              style={{ width: 160 }}
              value={status}
              onChange={setStatus}
              options={[
                { value: 'pending', label: 'En attente' },
                { value: 'active', label: 'Actif' },
                { value: 'blocked', label: 'Bloqué' },
              ]}
            />
            <Button icon={<ReloadOutlined />} onClick={() => refetch()} />
            {canCreate && (
              <Button type="primary" icon={<PlusOutlined />} onClick={() => setCreateOpen(true)}>
                Nouveau badge
              </Button>
            )}
          </Space>
        </Space>
        <Table
          rowKey="id"
          loading={isLoading}
          dataSource={data?.data ?? []}
          columns={columns as any}
          pagination={{
            current: data?.meta.current_page ?? 1,
            total: data?.meta.total ?? 0,
            pageSize: data?.meta.per_page ?? 15,
            onChange: setPage,
          }}
        />
      </Card>

      <Modal title="Nouveau badge RFID" open={createOpen} onCancel={() => setCreateOpen(false)} onOk={handleCreate} confirmLoading={createMutation.isPending}>
        <Form form={createForm} layout="vertical">
          <Form.Item name="user_id" label="Client propriétaire" rules={[{ required: true, message: 'Requis' }]}>
            <Select
              showSearch
              placeholder="Sélectionner un client"
              options={(clientsData?.data ?? []).map((u: any) => ({ value: u.id, label: `${u.name} (${u.email})` }))}
              filterOption={(input, option) => (option?.label as string)?.toLowerCase().includes(input.toLowerCase())}
            />
          </Form.Item>
          <Form.Item name="identifier" label="Identifiant brut du badge (idTag/idToken)" rules={[{ required: true, message: 'Requis' }]}>
            <Input placeholder="Ex : A1B2C3D4E5F6" />
          </Form.Item>
          <Form.Item name="label" label="Libellé (optionnel)">
            <Input placeholder="Ex : Badge principal" />
          </Form.Item>
          <Form.Item name="expires_at" label="Date d'expiration (optionnel)">
            <DatePicker showTime style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Modal>

      <Modal title="Réattribuer le badge" open={!!reassignBadge} onCancel={() => setReassignBadge(null)} onOk={handleReassign} confirmLoading={reassignMutation.isPending}>
        <Form form={reassignForm} layout="vertical">
          <Form.Item name="user_id" label="Nouveau propriétaire" rules={[{ required: true, message: 'Requis' }]}>
            <Select
              showSearch
              placeholder="Sélectionner un client"
              options={(clientsData?.data ?? []).map((u: any) => ({ value: u.id, label: `${u.name} (${u.email})` }))}
              filterOption={(input, option) => (option?.label as string)?.toLowerCase().includes(input.toLowerCase())}
            />
          </Form.Item>
        </Form>
      </Modal>

      <Modal title="Modifier l'expiration" open={!!expirationBadge} onCancel={() => setExpirationBadge(null)} onOk={handleExpiration} confirmLoading={expirationMutation.isPending}>
        <Form form={expirationForm} layout="vertical">
          <Form.Item name="expires_at" label="Date d'expiration">
            <DatePicker showTime style={{ width: '100%' }} allowClear />
          </Form.Item>
        </Form>
      </Modal>

      <Drawer title="Historique du badge" open={!!historyBadge} onClose={() => setHistoryBadge(null)} width={420}>
        <List
          dataSource={historyData?.data ?? []}
          renderItem={(h) => (
            <List.Item>
              <List.Item.Meta
                title={h.event_type}
                description={
                  <>
                    <div>{dayjs(h.created_at).format('DD/MM/YYYY HH:mm')} — source : {h.source}</div>
                    {h.performed_by && <div>Par : {h.performed_by.name}</div>}
                  </>
                }
              />
            </List.Item>
          )}
        />
      </Drawer>
    </AppShell>
  );
}
