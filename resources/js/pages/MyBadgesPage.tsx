import { Card, Table, Tag, Typography } from 'antd';
import dayjs from 'dayjs';
import { useRfidBadges } from '../api/rfid-badges';
import AppShell from '../components/AppShell';

const { Title } = Typography;

const STATUS_COLORS: Record<string, string> = {
  pending: 'default',
  active: 'green',
  blocked: 'red',
};

export default function MyBadgesPage() {
  const { data, isLoading } = useRfidBadges({});

  const columns = [
    { title: 'Indice', dataIndex: 'identifier_hint', key: 'identifier_hint', render: (v: string) => <code>{v}</code> },
    { title: 'Libellé', dataIndex: 'label', key: 'label', render: (v: string | null) => v || '—' },
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
  ];

  return (
    <AppShell>
      <Card>
        <Title level={3} style={{ marginTop: 0 }}>Mes badges RFID</Title>
        <Table rowKey="id" loading={isLoading} dataSource={data?.data ?? []} columns={columns as any} pagination={false} />
      </Card>
    </AppShell>
  );
}
