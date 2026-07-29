import { useParams, useNavigate } from 'react-router-dom';
import { Card, Descriptions, Button, Typography, Spin, Alert } from 'antd';
import { ArrowLeftOutlined, EditOutlined } from '@ant-design/icons';
import { useSite } from '../api/sites';
import { useOrganizations } from '../api/stations';
import { usePermission } from '../auth/AuthContext';

const { Title } = Typography;

export default function SiteDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const siteId = Number(id);
  const { data: site, isLoading } = useSite(siteId);
  const { data: organizations } = useOrganizations();
  const canUpdate = usePermission('sites.update');

  if (isLoading) return <div className="station-page-state"><Spin /></div>;
  if (!site) return <Alert className="station-page-error" type="error" message="Site introuvable" />;

  const org = organizations?.find((o) => o.id === site.organization_id);

  return (
    <main className="station-page station-page--detail">
      <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/sites')}>
        Retour à la liste
      </Button>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Title className="station-page-title" level={3}>{site.name}</Title>
        {canUpdate && (
          <Button icon={<EditOutlined />} onClick={() => navigate(`/sites/${siteId}/edit`)}>
            Modifier
          </Button>
        )}
      </div>

      <Card className="station-info-card">
        <Descriptions column={{ xs: 1, sm: 2 }} bordered size="small">
          <Descriptions.Item label="Nom">{site.name}</Descriptions.Item>
          <Descriptions.Item label="Organisation">{org?.name ?? `Org #${site.organization_id}`}</Descriptions.Item>
          <Descriptions.Item label="Adresse" span={2}>{site.address}</Descriptions.Item>
          <Descriptions.Item label="Latitude">{site.latitude ?? '—'}</Descriptions.Item>
          <Descriptions.Item label="Longitude">{site.longitude ?? '—'}</Descriptions.Item>
          <Descriptions.Item label="Créé le">{new Date(site.created_at).toLocaleString('fr-TN')}</Descriptions.Item>
          <Descriptions.Item label="Mis à jour">{new Date(site.updated_at).toLocaleString('fr-TN')}</Descriptions.Item>
        </Descriptions>
      </Card>
    </main>
  );
}
