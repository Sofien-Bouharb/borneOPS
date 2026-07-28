import { useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Form, Input, InputNumber, Button, Card, Typography, Spin, Alert, Space, message, Row, Col } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { useStation, useUpdateStation } from '../api/stations';

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

  if (isLoading) return <Spin style={{ display: 'block', margin: '100px auto' }} />;
  if (!station) return <Alert type="error" message="Borne introuvable" style={{ margin: 24 }} />;

  const onFinish = async (values: Record<string, unknown>) => {
    try {
      await updateMutation.mutateAsync({ id: stationId, ...values });
      message.success('Borne mise à jour');
      navigate(`/stations/${stationId}`);
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      if (error.response?.data?.errors) {
        const fields = Object.entries(error.response.data.errors).map(([name, errs]) => ({
          name,
          errors: errs,
        }));
        form.setFields(fields);
      } else {
        message.error(error.response?.data?.message ?? 'Erreur lors de la mise à jour');
      }
    }
  };

  return (
    <div style={{ padding: '24px', maxWidth: 800, margin: '0 auto' }}>
      <Button type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate(`/stations/${stationId}`)} style={{ marginBottom: 16, paddingLeft: 0 }}>
        Retour au détail
      </Button>

      <Title level={3}>Modifier — {station.name}</Title>

      {!isCommissioning && (
        <Alert
          type="info"
          message="Les champs sensibles (référence, n° série, identifiant OCPP, version OCPP) ne sont modifiables qu'en phase de commissioning."
          style={{ marginBottom: 16 }}
          showIcon
        />
      )}

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
              <Form.Item name="model" label="Modèle" rules={[{ required: true, message: 'Requis' }]}>
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="firmware_version" label="Version firmware">
                <Input />
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
              <Form.Item name="latitude" label="Latitude" rules={[{ required: true, message: 'Requis' }]}>
                <InputNumber min={-90} max={90} step={0.0000001} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="longitude" label="Longitude" rules={[{ required: true, message: 'Requis' }]}>
                <InputNumber min={-180} max={180} step={0.0000001} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>

          {isCommissioning && (
            <>
              <Title level={5} style={{ marginTop: 16 }}>Champs sensibles (commissioning uniquement)</Title>
              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item name="reference" label="Référence">
                    <Input />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item name="serial_number" label="Numéro de série">
                    <Input />
                  </Form.Item>
                </Col>
              </Row>
              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item name="ocpp_identifier" label="Identifiant OCPP">
                    <Input />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item name="ocpp_version" label="Version OCPP">
                    <Input disabled />
                  </Form.Item>
                </Col>
              </Row>
            </>
          )}

          <Form.Item name="reason" label="Raison de la modification">
            <Input />
          </Form.Item>

          <Form.Item style={{ marginBottom: 0, textAlign: 'right' }}>
            <Space>
              <Button onClick={() => navigate(`/stations/${stationId}`)}>Annuler</Button>
              <Button type="primary" htmlType="submit" loading={updateMutation.isPending}>
                Enregistrer
              </Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>
    </div>
  );
}
