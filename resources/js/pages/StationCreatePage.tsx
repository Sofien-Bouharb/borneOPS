import { useNavigate } from 'react-router-dom';
import { Form, Input, InputNumber, Select, Button, Card, Typography, message, Row, Col, Space } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { useCreateStation, useSites } from '../api/stations';

const { Title } = Typography;

export default function StationCreatePage() {
  const navigate = useNavigate();
  const [form] = Form.useForm();
  const createMutation = useCreateStation();
  const { data: sites } = useSites();

  const siteId = Form.useWatch('site_id', form);

  const onFinish = async (values: Record<string, unknown>) => {
    try {
      const station = await createMutation.mutateAsync(values);
      message.success('Borne créée avec succès');
      navigate(`/stations/${station.id}`);
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      if (error.response?.data?.errors) {
        const fields = Object.entries(error.response.data.errors).map(([name, errs]) => ({
          name,
          errors: errs,
        }));
        form.setFields(fields);
      } else {
        message.error(error.response?.data?.message ?? 'Erreur lors de la création');
      }
    }
  };

  return (
    <div style={{ padding: '24px', maxWidth: 800, margin: '0 auto' }}>
      <Button type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/stations')} style={{ marginBottom: 16, paddingLeft: 0 }}>
        Retour à la liste
      </Button>

      <Title level={3}>Nouvelle borne de recharge</Title>

      <Card>
        <Form form={form} layout="vertical" onFinish={onFinish}>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="name" label="Nom" rules={[{ required: true, message: 'Requis' }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="manufacturer" label="Fabricant" rules={[{ required: true, message: 'Requis' }]}>
                <Input />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="reference" label="Référence" rules={[{ required: true, message: 'Requis' }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="serial_number" label="Numéro de série" rules={[{ required: true, message: 'Requis' }]}>
                <Input />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="model" label="Modèle" rules={[{ required: true, message: 'Requis' }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="ocpp_version" label="Version OCPP" rules={[{ required: true, message: 'Requis' }]}>
                <Select options={[{ value: '1.6', label: 'OCPP 1.6' }, { value: '2.0.1', label: 'OCPP 2.0.1' }]} />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={8}>
              <Form.Item name="power_kw" label="Puissance (kW)" rules={[{ required: true, message: 'Requis' }]}>
                <InputNumber min={0.01} step={0.01} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="declared_connector_count" label="Connecteurs" rules={[{ required: true, message: 'Requis' }]}>
                <InputNumber min={1} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="ocpp_identifier" label="Identifiant OCPP">
                <Input />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="latitude" label="Latitude" rules={[{ required: true, message: 'Requis' }]}>
                <InputNumber min={-90} max={90} step={0.0000001} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="longitude" label="Longitude" rules={[{ required: true, message: 'Requis' }]}>
                <InputNumber min={-180} max={180} step={0.0000001} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="site_id" label="Site">
                <Select
                  allowClear
                  placeholder="Aucun site (commissioning)"
                  options={sites?.map((s) => ({ value: s.id, label: s.name })) ?? []}
                />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="firmware_version" label="Version firmware">
                <Input />
              </Form.Item>
            </Col>
          </Row>

          {!siteId && (
            <Form.Item name="address" label="Adresse (provisoire, effacée à l'affectation)">
              <Input />
            </Form.Item>
          )}

          <Form.Item style={{ marginBottom: 0, textAlign: 'right' }}>
            <Space>
              <Button onClick={() => navigate('/stations')}>Annuler</Button>
              <Button type="primary" htmlType="submit" loading={createMutation.isPending}>
                Créer la borne
              </Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>
    </div>
  );
}
