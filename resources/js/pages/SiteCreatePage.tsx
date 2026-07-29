import { useNavigate } from 'react-router-dom';
import { Form, Input, InputNumber, Select, Button, Card, Typography, message, Space, Row, Col } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { useCreateSite } from '../api/sites';
import { useOrganizations } from '../api/stations';
import { extractErrorMessage, translateBackendMessage } from '../utils/apiErrors';

const { Title } = Typography;

export default function SiteCreatePage() {
  const navigate = useNavigate();
  const [form] = Form.useForm();
  const createMutation = useCreateSite();
  const { data: organizations } = useOrganizations();

  const onFinish = async (values: Record<string, unknown>) => {
    try {
      const site = await createMutation.mutateAsync(values);
      message.success('Site créé avec succès');
      navigate(`/sites/${site.id}`);
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
    <main className="station-page station-page--form">
      <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/sites')}>
        Retour à la liste
      </Button>
      <Title className="station-page-title station-page-title--form" level={3}>Nouveau site</Title>

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
              <Button onClick={() => navigate('/sites')}>Annuler</Button>
              <Button type="primary" htmlType="submit" loading={createMutation.isPending}>
                Créer
              </Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>
    </main>
  );
}
