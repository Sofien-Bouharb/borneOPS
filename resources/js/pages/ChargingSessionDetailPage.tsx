import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card, Descriptions, Tag, Button, Space, Typography, Spin, Modal, Form,
  InputNumber, Select, Input, message, Alert, Row, Col,
} from 'antd';
import {
  ArrowLeftOutlined, PlayCircleOutlined, PauseCircleOutlined,
  CheckCircleOutlined, CloseCircleOutlined, ThunderboltOutlined,
} from '@ant-design/icons';
import { useChargingSession, useChargingSessionAction } from '../api/chargingSessions';
import '../../css/stations.css';
import { extractErrorMessage } from '../utils/apiErrors';
import { usePermission } from '../auth/AuthContext';
import AppShell from '../components/AppShell';

const { Title, Text } = Typography;

const STATUS_LABELS: Record<string, string> = {
  pending: 'En attente',
  active: 'En charge',
  paused: 'En pause',
  completed: 'Terminée',
  cancelled: 'Annulée',
};

const STATUS_COLORS: Record<string, string> = {
  pending: 'default',
  active: 'processing',
  paused: 'warning',
  completed: 'success',
  cancelled: 'error',
};

const REASON_LABELS: Record<string, string> = {
  user_requested: "Demande de l'utilisateur",
  operator_requested: "Demande de l'opérateur",
  remote_stop: 'Arrêt à distance',
  vehicle_disconnected: 'Véhicule débranché',
  equipment_fault: "Défaillance de l'équipement",
  equipment_unavailable: 'Équipement indisponible',
  power_loss: 'Coupure de courant',
  communication_loss: 'Perte de communication',
  other: 'Autre',
};

// Mirrors ChargingSessionService::COMPLETION_REASON_CODES on the backend —
// equipment_unavailable is deliberately excluded (cancellation-only).
const COMPLETION_REASON_CODES = [
  'user_requested', 'operator_requested', 'remote_stop', 'vehicle_disconnected',
  'equipment_fault', 'power_loss', 'communication_loss', 'other',
];

// Mirrors ChargingSessionService::CANCELLATION_REASON_CODES.
const CANCELLATION_REASON_CODES = [
  'user_requested', 'operator_requested', 'equipment_unavailable', 'other',
];

function formatDuration(seconds: number | null): string {
  if (seconds === null) return '—';
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  if (h > 0) return `${h}h ${m}min ${s}s`;
  if (m > 0) return `${m}min ${s}s`;
  return `${s}s`;
}

function formatDate(value: string | null): string {
  if (!value) return '—';
  return new Date(value).toLocaleString('fr-FR');
}

function StartModal({ open, onCancel, onOk, loading }: {
  open: boolean; onCancel: () => void; onOk: (values: { meter_start_wh: number }) => void; loading: boolean;
}) {
  const [form] = Form.useForm();
  return (
    <Modal
      open={open}
      title="Démarrer la session"
      onCancel={() => { form.resetFields(); onCancel(); }}
      onOk={() => form.validateFields().then((vals) => { onOk(vals); form.resetFields(); })}
      confirmLoading={loading}
      destroyOnClose
    >
      <Form form={form} layout="vertical">
        <Form.Item
          name="meter_start_wh"
          label="Relevé compteur initial (Wh)"
          rules={[{ required: true, message: 'Requis' }]}
        >
          <InputNumber style={{ width: '100%' }} min={0} />
        </Form.Item>
      </Form>
    </Modal>
  );
}

function EndModal({ open, onCancel, onOk, loading }: {
  open: boolean; onCancel: () => void; onOk: (values: { meter_stop_wh: number; reason_code: string; reason_detail?: string }) => void; loading: boolean;
}) {
  const [form] = Form.useForm();
  const reasonCode = Form.useWatch('reason_code', form);
  return (
    <Modal
      open={open}
      title="Terminer la session"
      onCancel={() => { form.resetFields(); onCancel(); }}
      onOk={() => form.validateFields().then((vals) => { onOk(vals); form.resetFields(); })}
      confirmLoading={loading}
      destroyOnClose
    >
      <Form form={form} layout="vertical">
        <Form.Item
          name="meter_stop_wh"
          label="Relevé compteur final (Wh)"
          rules={[{ required: true, message: 'Requis' }]}
        >
          <InputNumber style={{ width: '100%' }} min={0} />
        </Form.Item>
        <Form.Item
          name="reason_code"
          label="Motif"
          rules={[{ required: true, message: 'Requis' }]}
        >
          <Select options={COMPLETION_REASON_CODES.map((c) => ({ value: c, label: REASON_LABELS[c] }))} />
        </Form.Item>
        {reasonCode === 'other' && (
          <Form.Item
            name="reason_detail"
            label="Détail"
            rules={[{ required: true, message: 'Requis' }]}
          >
            <Input.TextArea rows={2} />
          </Form.Item>
        )}
      </Form>
    </Modal>
  );
}

function CancelModal({ open, onCancel, onOk, loading }: {
  open: boolean; onCancel: () => void; onOk: (values: { reason_code: string; reason_detail?: string }) => void; loading: boolean;
}) {
  const [form] = Form.useForm();
  const reasonCode = Form.useWatch('reason_code', form);
  return (
    <Modal
      open={open}
      title="Annuler la session"
      onCancel={() => { form.resetFields(); onCancel(); }}
      onOk={() => form.validateFields().then((vals) => { onOk(vals); form.resetFields(); })}
      confirmLoading={loading}
      destroyOnClose
    >
      <Form form={form} layout="vertical">
        <Form.Item
          name="reason_code"
          label="Motif"
          rules={[{ required: true, message: 'Requis' }]}
        >
          <Select options={CANCELLATION_REASON_CODES.map((c) => ({ value: c, label: REASON_LABELS[c] }))} />
        </Form.Item>
        {reasonCode === 'other' && (
          <Form.Item
            name="reason_detail"
            label="Détail"
            rules={[{ required: true, message: 'Requis' }]}
          >
            <Input.TextArea rows={2} />
          </Form.Item>
        )}
      </Form>
    </Modal>
  );
}

export default function ChargingSessionDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const sessionId = Number(id);

  const { data: session, isLoading } = useChargingSession(sessionId);
  const actionMutation = useChargingSessionAction();
  const [modal, setModal] = useState<'start' | 'end' | 'cancel' | null>(null);

  const canStart = usePermission('charging_sessions.start');
  const canPause = usePermission('charging_sessions.pause');
  const canEnd = usePermission('charging_sessions.end');
  const canCancel = usePermission('charging_sessions.cancel');

  if (isLoading) return <AppShell><div className="station-page-state"><Spin /></div></AppShell>;
  if (!session) return <AppShell><Alert className="station-page-error" type="error" message="Session introuvable" /></AppShell>;

  const isPending = session.status === 'pending';
  const isActive = session.status === 'active';
  const isPaused = session.status === 'paused';

  const doAction = async (action: 'start' | 'pause' | 'resume' | 'end' | 'cancel', payload?: Record<string, unknown>) => {
    try {
      await actionMutation.mutateAsync({ id: sessionId, action, payload });
      message.success('Action effectuée');
      setModal(null);
    } catch (err: unknown) {
      message.error(extractErrorMessage(err));
    }
  };

  return (
    <AppShell>
      <main className="station-page station-page--detail">
        <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/charging-sessions')}>
          Retour à la liste
        </Button>

        <Row className="station-page-header" justify="space-between" align="middle" gutter={[20, 16]}>
          <Col>
            <Space align="center">
              <Title className="station-page-title" level={3} style={{ marginBottom: 0 }}>
                Session #{session.id}
              </Title>
              <Tag color={STATUS_COLORS[session.status]}>{STATUS_LABELS[session.status]}</Tag>
            </Space>
          </Col>
          <Col>
            <Space wrap>
              {isPending && canStart && (
                <Button type="primary" icon={<PlayCircleOutlined />} onClick={() => setModal('start')}>
                  Démarrer
                </Button>
              )}
              {isPending && canCancel && (
                <Button danger icon={<CloseCircleOutlined />} onClick={() => setModal('cancel')}>
                  Annuler
                </Button>
              )}
              {isActive && canPause && (
                <Button icon={<PauseCircleOutlined />} onClick={() => doAction('pause')} loading={actionMutation.isPending}>
                  Mettre en pause
                </Button>
              )}
              {isPaused && canPause && (
                <Button type="primary" icon={<PlayCircleOutlined />} onClick={() => doAction('resume')} loading={actionMutation.isPending}>
                  Reprendre
                </Button>
              )}
              {(isActive || isPaused) && canEnd && (
                <Button icon={<CheckCircleOutlined />} onClick={() => setModal('end')}>
                  Terminer
                </Button>
              )}
            </Space>
          </Col>
        </Row>

        <Card className="station-form-card">
          <Descriptions bordered column={{ xs: 1, sm: 2 }} size="middle">
            <Descriptions.Item label="Borne">
              {session.charging_station ? (
                <a onClick={() => navigate(`/stations/${session.charging_station_id}`)}>
                  {session.charging_station.name}
                </a>
              ) : '—'}
            </Descriptions.Item>
            <Descriptions.Item label="Connecteur">
              {session.connector ? (
                <span><ThunderboltOutlined /> #{session.connector.connector_number} — {session.connector.standard.toUpperCase()}</span>
              ) : '—'}
            </Descriptions.Item>
            <Descriptions.Item label="Client">
              {session.customer ? `${session.customer.name} (${session.customer.email})` : <Text type="secondary">—</Text>}
            </Descriptions.Item>
            <Descriptions.Item label="Transaction OCPP">
              {session.ocpp_transaction_id ?? <Text type="secondary">—</Text>}
            </Descriptions.Item>

            <Descriptions.Item label="Relevé initial">
              {session.meter_start_wh !== null ? `${session.meter_start_wh} Wh` : '—'}
            </Descriptions.Item>
            <Descriptions.Item label="Relevé actuel">
              {session.latest_meter_wh !== null ? `${session.latest_meter_wh} Wh` : '—'}
            </Descriptions.Item>
            <Descriptions.Item label="Relevé final">
              {session.meter_stop_wh !== null ? `${session.meter_stop_wh} Wh` : '—'}
            </Descriptions.Item>
            <Descriptions.Item label="Énergie consommée">
              {session.energy_consumed_kwh !== null ? `${session.energy_consumed_kwh} kWh` : '—'}
            </Descriptions.Item>

            <Descriptions.Item label="Durée totale">{formatDuration(session.duration_seconds)}</Descriptions.Item>
            <Descriptions.Item label="Durée de charge">{formatDuration(session.charging_seconds)}</Descriptions.Item>
            <Descriptions.Item label="Temps en pause">
              {session.total_paused_seconds !== null ? formatDuration(session.total_paused_seconds) : '—'}
            </Descriptions.Item>
            <Descriptions.Item label="Prix total">
              {session.total_price ? `${session.total_price} ${session.currency ?? ''}` : <Text type="secondary">Non facturé</Text>}
            </Descriptions.Item>

            <Descriptions.Item label="Créée le">{formatDate(session.created_at)}</Descriptions.Item>
            <Descriptions.Item label="Démarrée le">{formatDate(session.started_at)}</Descriptions.Item>
            <Descriptions.Item label="Terminée le">{formatDate(session.completed_at)}</Descriptions.Item>
            <Descriptions.Item label="Annulée le">{formatDate(session.cancelled_at)}</Descriptions.Item>

            {session.reason_code && (
              <Descriptions.Item label="Motif" span={2}>
                {REASON_LABELS[session.reason_code] ?? session.reason_code}
                {session.reason_detail && ` — ${session.reason_detail}`}
              </Descriptions.Item>
            )}
          </Descriptions>
        </Card>

        <StartModal
          open={modal === 'start'}
          onCancel={() => setModal(null)}
          onOk={(vals) => doAction('start', vals)}
          loading={actionMutation.isPending}
        />
        <EndModal
          open={modal === 'end'}
          onCancel={() => setModal(null)}
          onOk={(vals) => doAction('end', vals)}
          loading={actionMutation.isPending}
        />
        <CancelModal
          open={modal === 'cancel'}
          onCancel={() => setModal(null)}
          onOk={(vals) => doAction('cancel', vals)}
          loading={actionMutation.isPending}
        />
      </main>
    </AppShell>
  );
}
