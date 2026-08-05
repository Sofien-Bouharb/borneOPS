import { useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Form, Input, InputNumber, Select, Button, Card, Typography, Spin, Alert, Space, message, Row, Col } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { useSite, useUpdateSite } from '../api/sites';
import { useOrganizations } from '../api/stations';
import { extractErrorMessage, translateBackendMessage } from '../utils/apiErrors';
import AppShell from '../components/AppShell';

const { Title } = Typography;

export default function SiteEditPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const siteId = Number(id);
  const [form] = Form.useForm();
  const { data: site, isLoading } = useSite(siteId);
  const { data: organizations } = useOrganizations();
  const updateMutation = useUpdateSite();

  useEffect(() => {
    if (site) {
      form.setFieldsValue({
        organization_id: site.organization_id,
        name: site.name,
        address: site.address,
        latitude: site.latitude ? Number(site.latitude) : undefined,
        longitude: site.longitude ? Number(site.longitude) : undefined,
      });
    }
  }, [site, form]);

  if (isLoading) return <AppShell><div className="station-page-state"><Spin /></div></AppShell>;
  if (!site) return <AppShell><Alert className="station-page-error" type="error" message="Site introuvable" /></AppShell>;

  const onFinish = async (values: Record<string, unknown>) => {
    try {
      await updateMutation.mutateAsync({ id: siteId, ...values });
      message.success('Site mis à jour');
      navigate(`/sites/${siteId}`);
    } catch (err: unknown) {
      const error = err as { response?: { data?: { errors?: Record<string, string[]> } } };
      if (error.response?.data?.errors) {
        const fields = Object.entries(error.response.data.errors).map(([name, errs]) => ({
          name, errors: errs.map((m) => translateBackendMessage(m)),
        }));
        form.setFields(fields);
      } else {
        message.error(extractErrorMessage(err));
      }
    }
  };

  return (
    <AppShell>
      <main className="station-page station-page--form">
        <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate(`/sites/${siteId}`)}>
          Retour au détail
        </Button>
        <Title className="station-page-title station-page-title--form" level={3}>Modifier — {site.name}</Title>

        <Card className="station-form-card">
          <Form className="station-form" form={form} layout="vertical" onFinish={onFinish}>
            <Form.Item name="organization_id" label="Organisation" rules={[{ required: true, message: 'Requis' }]}>
              <Select
                placeholder="Sélectionner une organisation"
                options={organizations?.map((o) => ({ value: o.id, label: o.name })) ?? []}
              />
            </Form.Item>
            <Form.Item name="name" label="Nom" rules={[{ required: true, message: 'Requis' }]}>
              <Input />
            </Form.Item>
            <Form.Item name="address" label="Adresse" rules={[{ required: true, message: 'Requis' }]}>
              <Input.TextArea rows={2} />
            </Form.Item>
            <Row gutter={[20, 0]}>
              <Col xs={24} sm={12}>
                <Form.Item name="latitude" label="Latitude">
                  <InputNumber className="station-form-control" min={-90} max={90} step={0.0000001} />
                </Form.Item>
              </Col>
              <Col xs={24} sm={12}>
                <Form.Item name="longitude" label="Longitude">
                  <InputNumber className="station-form-control" min={-180} max={180} step={0.0000001} />
                </Form.Item>
              </Col>
            </Row>

            <Form.Item className="station-form-actions">
              <Space>
                <Button onClick={() => navigate(`/sites/${siteId}`)}>Annuler</Button>
                <Button type="primary" htmlType="submit" loading={updateMutation.isPending}>
                  Enregistrer
                </Button>
              </Space>
            </Form.Item>
          </Form>
        </Card>
      </main>
    </AppShell>
  );
}
