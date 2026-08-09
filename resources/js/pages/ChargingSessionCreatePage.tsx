import { useNavigate } from 'react-router-dom';
import { Form, Select, Button, Card, Typography, message, Space, Alert } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { useCreateChargingSession } from '../api/chargingSessions';
import { useStations } from '../api/stations';
import { useConnectors } from '../api/connectors';
import '../../css/stations.css';
import { extractErrorMessage, translateBackendMessage } from '../utils/apiErrors';
import AppShell from '../components/AppShell';

const { Title } = Typography;

export default function ChargingSessionCreatePage() {
  const navigate = useNavigate();
  const [form] = Form.useForm();
  const createMutation = useCreateChargingSession();

  const { data: stationsResponse } = useStations({ administrative_status: 'active', per_page: 100 });
  const eligibleStations = stationsResponse?.data ?? [];

  const stationId = Form.useWatch('charging_station_id', form);
  const { data: connectors } = useConnectors(stationId);
  const eligibleConnectors = (connectors ?? []).filter(
    (c) => c.administrative_status === 'enabled' && c.operational_status === 'available',
  );

  const onFinish = async (values: Record<string, unknown>) => {
    try {
      const session = await createMutation.mutateAsync(values as {
        charging_station_id: number;
        connector_id: number;
      });
      message.success('Session de recharge créée avec succès');
      navigate(`/charging-sessions/${session.id}`);
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
    <AppShell>
      <main className="station-page station-page--form">
        <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/charging-sessions')}>
          Retour à la liste
        </Button>

        <Title className="station-page-title station-page-title--form" level={3}>Nouvelle session de recharge</Title>

        <Card className="station-form-card">
          <Alert
            style={{ marginBottom: 20 }}
            type="info"
            showIcon
            title="Seules les bornes actives et connectées, ainsi que leurs connecteurs activés et disponibles, apparaissent ci-dessous."
          />

          <Form className="station-form" form={form} layout="vertical" onFinish={onFinish}>
            <Form.Item
              name="charging_station_id"
              label="Borne"
              rules={[{ required: true, message: 'Requis' }]}
            >
              <Select
                placeholder="Sélectionner une borne"
                showSearch
                optionFilterProp="label"
                onChange={() => form.setFieldValue('connector_id', undefined)}
                options={eligibleStations.map((s) => ({ value: s.id, label: `${s.name} (${s.reference})` }))}
                notFoundContent="Aucune borne éligible trouvée"
              />
            </Form.Item>

            <Form.Item
              name="connector_id"
              label="Connecteur"
              rules={[{ required: true, message: 'Requis' }]}
            >
              <Select
                placeholder={stationId ? 'Sélectionner un connecteur' : 'Choisissez d\'abord une borne'}
                disabled={!stationId}
                options={eligibleConnectors.map((c) => ({
                  value: c.id,
                  label: `Connecteur #${c.connector_number} — ${c.standard.toUpperCase()}`,
                }))}
                notFoundContent="Aucun connecteur éligible sur cette borne"
              />
            </Form.Item>

            <Form.Item className="station-form-actions">
              <Space>
                <Button onClick={() => navigate('/charging-sessions')}>Annuler</Button>
                <Button type="primary" htmlType="submit" loading={createMutation.isPending}>
                  Créer la session
                </Button>
              </Space>
            </Form.Item>
          </Form>
        </Card>
      </main>
    </AppShell>
  );
}
