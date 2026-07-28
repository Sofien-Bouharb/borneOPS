import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card, Descriptions, Tag, Button, Space, Typography, Spin, Modal, Form,
  Input, Select, Timeline, Pagination, message, Alert, Row, Col, Divider,
} from 'antd';
import {
  ArrowLeftOutlined, EditOutlined, StopOutlined, CheckCircleOutlined,
  CloseCircleOutlined, SwapOutlined, ThunderboltOutlined,
} from '@ant-design/icons';
import { useStation, useStationHistory, useStationAction, useSites } from '../api/stations';
import '../../css/stations.css';

const { Title, Text } = Typography;

const ADMIN_COLORS: Record<string, string> = {
  commissioning: 'processing', active: 'success', disabled: 'warning', decommissioned: 'default',
};
const OP_COLORS: Record<string, string> = {
  available: 'success', occupied: 'processing', out_of_service: 'error',
  maintenance: 'warning', disconnected: 'default', fault: 'error',
};
const EVENT_COLORS: Record<string, string> = {
  created: 'blue', updated: 'gray', state_changed: 'orange',
  assigned: 'cyan', disabled: 'red', reactivated: 'green', decommissioned: 'gray',
};

function ActionModal({
  open, title, onCancel, onOk, loading, reasonRequired = true, children,
}: {
  open: boolean; title: string; onCancel: () => void;
  onOk: (values: { reason?: string; comment?: string } & Record<string, unknown>) => void;
  loading: boolean; reasonRequired?: boolean; children?: React.ReactNode;
}) {
  const [form] = Form.useForm();
  return (
    <Modal
      className="station-action-modal"
      open={open} title={title} onCancel={() => { form.resetFields(); onCancel(); }}
      onOk={() => form.validateFields().then((vals) => { onOk(vals); form.resetFields(); })}
      confirmLoading={loading} destroyOnClose
    >
      <Form className="station-action-form" form={form} layout="vertical">
        {children}
        <Form.Item name="reason" label="Raison" rules={reasonRequired ? [{ required: true, message: 'Requis' }] : []}>
          <Input />
        </Form.Item>
        <Form.Item name="comment" label="Commentaire">
          <Input.TextArea rows={2} />
        </Form.Item>
      </Form>
    </Modal>
  );
}

export default function StationDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const stationId = Number(id);

  const { data: station, isLoading } = useStation(stationId);
  const [historyPage, setHistoryPage] = useState(1);
  const { data: historyData } = useStationHistory(stationId, historyPage);
  const actionMutation = useStationAction();
  const { data: sites } = useSites();

  const [modal, setModal] = useState<string | null>(null);

  if (isLoading) return <div className="station-page-state"><Spin /></div>;
  if (!station) return <Alert className="station-page-error" type="error" message="Borne introuvable" />;

  const isDecommissioned = station.administrative_status === 'decommissioned';
  const isCommissioning = station.administrative_status === 'commissioning';
  const isActive = station.administrative_status === 'active';
  const isDisabled = station.administrative_status === 'disabled';

  const doAction = async (action: string, payload: Record<string, unknown>) => {
    try {
      await actionMutation.mutateAsync({ id: stationId, action, payload });
      message.success('Action effectuée');
      setModal(null);
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      message.error(error.response?.data?.message ?? 'Erreur');
    }
  };

  return (
    <main className="station-page station-page--detail">
      <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/stations')}>
        Retour à la liste
      </Button>

      <Row className="station-detail-header" justify="space-between" align="middle" gutter={[24, 18]}>
        <Col className="station-detail-heading">
          <Title className="station-page-title" level={3}>{station.name}</Title>
          <Space className="station-status-list" wrap>
            <Tag className="station-status-tag" color={ADMIN_COLORS[station.administrative_status]}>
              {station.administrative_status}
            </Tag>
            <Tag className="station-status-tag" color={OP_COLORS[station.operational_status]}>
              {station.operational_status}
            </Tag>
          </Space>
        </Col>
        <Col>
          {!isDecommissioned && (
            <Space className="station-detail-actions" wrap>
              <Button icon={<EditOutlined />} onClick={() => navigate(`/stations/${stationId}/edit`)}>
                Modifier
              </Button>
              {(isCommissioning || isDisabled) && (
                <Button className="station-action--positive" type="primary" icon={<CheckCircleOutlined />} onClick={() => setModal('reactivate')}>
                  {isCommissioning ? 'Activer' : 'Réactiver'}
                </Button>
              )}
              {isActive && (
                <Button className="station-action--warning" danger icon={<StopOutlined />} onClick={() => setModal('disable')}>
                  Désactiver
                </Button>
              )}
              <Button className="station-action--neutral" icon={<ThunderboltOutlined />} onClick={() => setModal('state')}>
                État opérationnel
              </Button>
              <Button icon={<SwapOutlined />} onClick={() => setModal('assignment')}>
                Affecter
              </Button>
              <Button className="station-action--destructive" type="primary" danger icon={<CloseCircleOutlined />} onClick={() => setModal('decommission')}>
                Décommissionner
              </Button>
            </Space>
          )}
        </Col>
      </Row>

      <Card className="station-info-card">
        <Descriptions className="station-descriptions" column={{ xs: 1, sm: 2 }} bordered size="small">
          <Descriptions.Item label="Référence">{station.reference}</Descriptions.Item>
          <Descriptions.Item label="N° série">{station.serial_number}</Descriptions.Item>
          <Descriptions.Item label="Modèle">{station.model}</Descriptions.Item>
          <Descriptions.Item label="Fabricant">{station.manufacturer}</Descriptions.Item>
          <Descriptions.Item label="Version OCPP">{station.ocpp_version}</Descriptions.Item>
          <Descriptions.Item label="Identifiant OCPP">{station.ocpp_identifier ?? '—'}</Descriptions.Item>
          <Descriptions.Item label="Firmware">{station.firmware_version ?? '—'}</Descriptions.Item>
          <Descriptions.Item label="Puissance">{station.power_kw} kW</Descriptions.Item>
          <Descriptions.Item label="Connecteurs">{station.declared_connector_count}</Descriptions.Item>
          <Descriptions.Item label="Site">{station.site_id ?? '— Non affectée'}</Descriptions.Item>
          <Descriptions.Item label="Coordonnées">{station.latitude}, {station.longitude}</Descriptions.Item>
          <Descriptions.Item label="Adresse">{station.address ?? '—'}</Descriptions.Item>
          <Descriptions.Item label="Créée le">{new Date(station.created_at).toLocaleString('fr-TN')}</Descriptions.Item>
          <Descriptions.Item label="Mise à jour">{new Date(station.updated_at).toLocaleString('fr-TN')}</Descriptions.Item>
        </Descriptions>
      </Card>

      <section className="station-history-section">
        <Divider className="station-section-divider" orientation={'left' as any}>Historique</Divider>

        <Card className="station-history-card">
          {historyData?.data.length ? (
            <>
              <Timeline
                className="station-history-timeline"
                items={historyData.data.map((h) => ({
                  color: EVENT_COLORS[h.event_type] ?? 'gray',
                  children: (
                    <div className="station-history-entry" key={h.id}>
                      <div className="station-history-entry__heading">
                        <Text strong>{h.event_type}</Text>
                        <span className="station-history-entry__meta">
                          <Text type="secondary">
                            {new Date(h.created_at).toLocaleString('fr-TN')}
                          </Text>
                          {h.performed_by && (
                            <Text type="secondary">
                              par {h.performed_by.name}
                            </Text>
                          )}
                        </span>
                      </div>
                      {h.reason && (
                        <div className="station-history-entry__reason">
                          <Text type="secondary">Raison : {h.reason}</Text>
                        </div>
                      )}
                      {h.old_values && (
                        <div className="station-history-entry__change">
                          <Text code>{JSON.stringify(h.old_values)}</Text>
                          <span aria-hidden="true">→</span>
                          <Text code>{JSON.stringify(h.new_values)}</Text>
                        </div>
                      )}
                    </div>
                  ),
                }))}
              />
              <Pagination
                className="station-history-pagination"
                current={historyData.meta.current_page}
                total={historyData.meta.total}
                pageSize={historyData.meta.per_page}
                onChange={setHistoryPage}
                size="small"
              />
            </>
          ) : (
            <div className="station-history-empty">
              <Text type="secondary">Aucun historique</Text>
            </div>
          )}
        </Card>
      </section>

      <ActionModal open={modal === 'disable'} title="Désactiver la borne" onCancel={() => setModal(null)}
        onOk={(vals) => doAction('disable', vals)} loading={actionMutation.isPending} />

      <ActionModal open={modal === 'reactivate'} title="Réactiver la borne" onCancel={() => setModal(null)}
        onOk={(vals) => doAction('reactivate', vals)} loading={actionMutation.isPending} reasonRequired={false} />

      <ActionModal open={modal === 'decommission'} title="Décommissionner la borne" onCancel={() => setModal(null)}
        onOk={(vals) => doAction('decommission', vals)} loading={actionMutation.isPending} />

      <ActionModal open={modal === 'state'} title="Changer l'état opérationnel" onCancel={() => setModal(null)}
        onOk={(vals) => doAction('state', vals)} loading={actionMutation.isPending}>
        <Form.Item name="operational_status" label="Nouvel état" rules={[{ required: true, message: 'Requis' }]}>
          <Select options={[
            { value: 'available', label: 'Available' },
            { value: 'occupied', label: 'Occupied' },
            { value: 'out_of_service', label: 'Out of service' },
            { value: 'maintenance', label: 'Maintenance' },
            { value: 'disconnected', label: 'Disconnected' },
            { value: 'fault', label: 'Fault' },
          ]} />
        </Form.Item>
      </ActionModal>

      <ActionModal open={modal === 'assignment'} title="Affecter à un site" onCancel={() => setModal(null)}
        onOk={(vals) => doAction('assignment', vals)} loading={actionMutation.isPending} reasonRequired={false}>
        <Form.Item name="site_id" label="Site">
          <Select
            allowClear
            placeholder="Aucun site (désaffecter)"
            options={sites?.map((s) => ({ value: s.id, label: s.name })) ?? []}
          />
        </Form.Item>
      </ActionModal>
    </main>
  );
}
