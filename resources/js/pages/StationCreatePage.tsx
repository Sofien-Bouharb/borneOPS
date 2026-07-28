import { useNavigate } from 'react-router-dom';
import { Form, Input, InputNumber, Select, Button, Card, Typography, message, Row, Col, Space } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { useCreateStation, useSites } from '../api/stations';
import '../../css/stations.css';

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
    <main className="station-page station-page--form">
      <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/stations')}>
        Retour à la liste
      </Button>

      <Title className="station-page-title station-page-title--form" level={3}>Nouvelle borne de recharge</Title>

      <Card className="station-form-card">
        <Form className="station-form" form={form} layout="vertical" onFinish={onFinish}>
          <section className="station-form-section">
            <Title className="station-form-section__title" level={5}>Identification</Title>

            <Row gutter={[20, 0]}>
              <Col xs={24} sm={12}>
                <Form.Item name="name" label="Nom" rules={[{ required: true, message: 'Requis' }]}>
                  <Input />
                </Form.Item>
              </Col>
              <Col xs={24} sm={12}>
                <Form.Item name="manufacturer" label="Fabricant" rules={[{ required: true, message: 'Requis' }]}>
                  <Input />
                </Form.Item>
              </Col>
            </Row>

            <Row gutter={[20, 0]}>
              <Col xs={24} sm={12}>
                <Form.Item name="reference" label="Référence" rules={[{ required: true, message: 'Requis' }]}>
                  <Input />
                </Form.Item>
              </Col>
              <Col xs={24} sm={12}>
                <Form.Item name="serial_number" label="Numéro de série" rules={[{ required: true, message: 'Requis' }]}>
                  <Input />
                </Form.Item>
              </Col>
            </Row>

            <Row gutter={[20, 0]}>
              <Col xs={24} sm={12}>
                <Form.Item name="model" label="Modèle" rules={[{ required: true, message: 'Requis' }]}>
                  <Input />
                </Form.Item>
              </Col>
              <Col xs={24} sm={12}>
                <Form.Item name="firmware_version" label="Version firmware">
                  <Input />
                </Form.Item>
              </Col>
            </Row>
          </section>

          <section className="station-form-section">
            <Title className="station-form-section__title" level={5}>Configuration technique</Title>

            <Row gutter={[20, 0]}>
              <Col xs={24} sm={12}>
                <Form.Item name="ocpp_version" label="Version OCPP" rules={[{ required: true, message: 'Requis' }]}>
                  <Select options={[{ value: '1.6', label: 'OCPP 1.6' }, { value: '2.0.1', label: 'OCPP 2.0.1' }]} />
                </Form.Item>
              </Col>
              <Col xs={24} sm={12}>
                <Form.Item name="ocpp_identifier" label="Identifiant OCPP">
                  <Input />
                </Form.Item>
              </Col>
            </Row>

            <Row gutter={[20, 0]}>
              <Col xs={24} sm={12}>
                <Form.Item name="power_kw" label="Puissance (kW)" rules={[{ required: true, message: 'Requis' }]}>
                  <InputNumber className="station-form-control" min={0.01} step={0.01} />
                </Form.Item>
              </Col>
              <Col xs={24} sm={12}>
                <Form.Item name="declared_connector_count" label="Connecteurs" rules={[{ required: true, message: 'Requis' }]}>
                  <InputNumber className="station-form-control" min={1} />
                </Form.Item>
              </Col>
            </Row>
          </section>

          <section className="station-form-section">
            <Title className="station-form-section__title" level={5}>Localisation et affectation</Title>

            <Row gutter={[20, 0]}>
              <Col xs={24} sm={12}>
                <Form.Item name="latitude" label="Latitude" rules={[{ required: true, message: 'Requis' }]}>
                  <InputNumber className="station-form-control" min={-90} max={90} step={0.0000001} />
                </Form.Item>
              </Col>
              <Col xs={24} sm={12}>
                <Form.Item name="longitude" label="Longitude" rules={[{ required: true, message: 'Requis' }]}>
                  <InputNumber className="station-form-control" min={-180} max={180} step={0.0000001} />
                </Form.Item>
              </Col>
            </Row>

            <Form.Item name="site_id" label="Site">
              <Select
                allowClear
                placeholder="Aucun site (commissioning)"
                options={sites?.map((s) => ({ value: s.id, label: s.name })) ?? []}
              />
            </Form.Item>

            {!siteId && (
              <Form.Item name="address" label="Adresse (provisoire, effacée à l'affectation)">
                <Input />
              </Form.Item>
            )}
          </section>

          <Form.Item className="station-form-actions">
            <Space>
              <Button onClick={() => navigate('/stations')}>Annuler</Button>
              <Button type="primary" htmlType="submit" loading={createMutation.isPending}>
                Créer la borne
              </Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>
    </main>
  );
}
