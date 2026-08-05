import { useParams, useNavigate } from 'react-router-dom';
import { Card, Descriptions, Tag, Button, Typography, Spin, Alert } from 'antd';
import { ArrowLeftOutlined, EditOutlined } from '@ant-design/icons';
import { useOrganization } from '../api/organizations';
import { usePermission } from '../auth/AuthContext';
import AppShell from '../components/AppShell';

const { Title } = Typography;

export default function OrganizationDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const orgId = Number(id);
  const { data: org, isLoading } = useOrganization(orgId);
  const canUpdate = usePermission('organizations.update');

  if (isLoading) return <AppShell><div className="station-page-state"><Spin /></div></AppShell>;
  if (!org) return <AppShell><Alert className="station-page-error" type="error" message="Organisation introuvable" /></AppShell>;

  return (
    <AppShell>
      <main className="station-page station-page--detail">
        <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/organizations')}>
          Retour à la liste
        </Button>

        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <div>
            <Title className="station-page-title" level={3}>{org.name}</Title>
            <Tag color={org.type === 'operator' ? 'blue' : 'green'}>
              {org.type === 'operator' ? 'Opérateur' : 'Client'}
            </Tag>
          </div>
          {canUpdate && (
            <Button icon={<EditOutlined />} onClick={() => navigate(`/organizations/${orgId}/edit`)}>
              Modifier
            </Button>
          )}
        </div>

        <Card className="station-info-card">
          <Descriptions column={{ xs: 1, sm: 2 }} bordered size="small">
            <Descriptions.Item label="Nom">{org.name}</Descriptions.Item>
            <Descriptions.Item label="Type">{org.type === 'operator' ? 'Opérateur' : 'Client'}</Descriptions.Item>
            <Descriptions.Item label="Email">{org.contact_email ?? '—'}</Descriptions.Item>
            <Descriptions.Item label="Téléphone">{org.contact_phone ?? '—'}</Descriptions.Item>
            <Descriptions.Item label="Adresse" span={2}>{org.address ?? '—'}</Descriptions.Item>
            <Descriptions.Item label="Créée le">{new Date(org.created_at).toLocaleString('fr-TN')}</Descriptions.Item>
            <Descriptions.Item label="Mise à jour">{new Date(org.updated_at).toLocaleString('fr-TN')}</Descriptions.Item>
          </Descriptions>
        </Card>
      </main>
    </AppShell>
  );
}
