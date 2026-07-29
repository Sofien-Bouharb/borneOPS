import { useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Form, Input, InputNumber, Button, Card, Typography, Spin, Alert, Space, message, Row, Col } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { useStation, useUpdateStation } from '../api/stations';
import '../../css/stations.css';
import { extractErrorMessage, translateBackendMessage } from '../utils/apiErrors';


const { Title } = Typography;

export default function StationEditPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const stationId = Number(id);
  const [form] = Form.useForm();
  const { data: station, isLoading } = useStation(stationId);
  const updateMutation = useUpdateStation();

  const isCommissioning = station?.administrative_status === 'commissioning';

  useEffect(() => {
    if (station) {
      form.setFieldsValue({
        name: station.name,
        model: station.model,
        manufacturer: station.manufacturer,
        firmware_version: station.firmware_version,
        power_kw: Number(station.power_kw),
        latitude: Number(station.latitude),
        longitude: Number(station.longitude),
        ...(isCommissioning ? {
          reference: station.reference,
          serial_number: station.serial_number,
          ocpp_version: station.ocpp_version,
          ocpp_identifier: station.ocpp_identifier,
        } : {}),
      });
    }
  }, [station, form, isCommissioning]);

  if (isLoading) return <div className="station-page-state"><Spin /></div>;
  if (!station) return <Alert className="station-page-error" type="error" message="Borne introuvable" />;

  const onFinish = async (values: Record<string, unknown>) => {
    try {
      await updateMutation.mutateAsync({ id: stationId, ...values });
      message.success('Borne mise à jour');
      navigate(`/stations/${stationId}`);
    } catch (err: unknown) {
      const error = err as { response?: { data?: { errors?: Record<string, string[]> } } };
      if (error.response?.data?.errors) {
        const fields = Object.entries(error.response.data.errors).map(([name, errs]) => ({
          name,
          errors: errs.map((msg) => translateBackendMessage(msg)),
        }));
        form.setFields(fields);
      } else {
        message.error(extractErrorMessage(err));
      }
    }
  };

  return (
    <main className="station-page station-page--form">
      <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate(`/stations/${stationId}`)}>
        Retour au détail
      </Button>

      <Title className="station-page-title station-page-title--form" level={3}>Modifier — {station.name}</Title>

      {!isCommissioning && (
        <Alert
          className="station-form-alert"
          type="info"
          message="Les champs sensibles (référence, n° série, identifiant OCPP, version OCPP) ne sont modifiables qu'en phase de commissioning."
          showIcon
        />
      )}

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
            <Title className="station-form-section__title" level={5}>Capacité et localisation</Title>

            <Row gutter={[20, 0]}>
              <Col xs={24} sm={8}>
                <Form.Item name="power_kw" label="Puissance (kW)" rules={[{ required: true, message: 'Requis' }]}>
                  <InputNumber className="station-form-control" min={0.01} step={0.01} />
                </Form.Item>
              </Col>
              <Col xs={24} sm={8}>
                <Form.Item name="latitude" label="Latitude" rules={[{ required: true, message: 'Requis' }]}>
                  <InputNumber className="station-form-control" min={-90} max={90} step={0.0000001} />
                </Form.Item>
              </Col>
              <Col xs={24} sm={8}>
                <Form.Item name="longitude" label="Longitude" rules={[{ required: true, message: 'Requis' }]}>
                  <InputNumber className="station-form-control" min={-180} max={180} step={0.0000001} />
                </Form.Item>
              </Col>
            </Row>
          </section>

          {isCommissioning && (
            <section className="station-form-section station-form-section--sensitive">
              <Title className="station-form-section__title" level={5}>Champs sensibles (commissioning uniquement)</Title>
              <Row gutter={[20, 0]}>
                <Col xs={24} sm={12}>
                  <Form.Item name="reference" label="Référence">
                    <Input />
                  </Form.Item>
                </Col>
                <Col xs={24} sm={12}>
                  <Form.Item name="serial_number" label="Numéro de série">
                    <Input />
                  </Form.Item>
                </Col>
              </Row>
              <Row gutter={[20, 0]}>
                <Col xs={24} sm={12}>
                  <Form.Item name="ocpp_identifier" label="Identifiant OCPP">
                    <Input />
                  </Form.Item>
                </Col>
                <Col xs={24} sm={12}>
                  <Form.Item name="ocpp_version" label="Version OCPP">
                    <Input disabled />
                  </Form.Item>
                </Col>
              </Row>
            </section>
          )}

          <section className="station-form-section station-form-section--last">
            <Form.Item name="reason" label="Raison de la modification">
              <Input />
            </Form.Item>
          </section>

          <Form.Item className="station-form-actions">
            <Space>
              <Button onClick={() => navigate(`/stations/${stationId}`)}>Annuler</Button>
              <Button type="primary" htmlType="submit" loading={updateMutation.isPending}>
                Enregistrer
              </Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>
    </main>
  );
}
