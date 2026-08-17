import { useState } from 'react';
import {
  Card, Table, Tag, Button, Space, Modal, Form, Select, InputNumber,
  Popconfirm, message, Alert,
} from 'antd';
import {
  PlusOutlined, EditOutlined, DeleteOutlined, ThunderboltOutlined, PoweroffOutlined,
} from '@ant-design/icons';
import {
  Connector,
  useConnectors,
  useCreateConnector,
  useUpdateConnector,
  useDeleteConnector,
  useUpdateConnectorState,
  useUpdateConnectorAvailability,
} from '../api/connectors';
import {
  OP_STATUS_LABELS,
  STANDARD_LABELS,
  CURRENT_TYPE_LABELS,
  CONNECTOR_ADMIN_STATUS_LABELS,
} from '../utils/stationLabels';
import { extractErrorMessage } from '../utils/apiErrors';
import { usePermission } from '../auth/AuthContext';

const OP_COLORS: Record<string, string> = {
  available: 'success', occupied: 'processing', out_of_service: 'error',
  maintenance: 'warning', disconnected: 'default', fault: 'error',
};

const ADMIN_COLORS: Record<string, string> = {
  enabled: 'success',
  disabled: 'default',
};

interface ConnectorsPanelProps {
  stationId: number;
  stationAdministrativeStatus: string;
  stationPowerKw: string;
  stationConnectionStatus: 'connected' | 'disconnected';
}

type ModalMode =
  | { type: 'create' }
  | { type: 'edit'; connector: Connector }
  | { type: 'state'; connector: Connector }
  | null;

function isValidationError(err: unknown): boolean {
  return !!err && typeof err === 'object' && 'errorFields' in err;
}

export default function ConnectorsPanel({
  stationId,
  stationAdministrativeStatus,
  stationPowerKw,
  stationConnectionStatus,
}: ConnectorsPanelProps) {
  const { data: connectors, isLoading } = useConnectors(stationId);
  const createMutation = useCreateConnector();
  const updateMutation = useUpdateConnector();
  const deleteMutation = useDeleteConnector();
  const stateMutation = useUpdateConnectorState();
  const availabilityMutation = useUpdateConnectorAvailability();

  const [modal, setModal] = useState<ModalMode>(null);
  const [form] = Form.useForm();
  const [stateForm] = Form.useForm();

  const canCreate = usePermission('connectors.create');
  const canUpdate = usePermission('connectors.update');
  const canDelete = usePermission('connectors.delete');
  const canStateUpdate = usePermission('connectors.state.update');
  const canAvailabilityUpdate = usePermission('connectors.availability.update');

  const isCommissioning = stationAdministrativeStatus === 'commissioning';
  const isDecommissioned = stationAdministrativeStatus === 'decommissioned';
  const isOcppConnected = stationConnectionStatus === 'connected';

  const openCreate = () => {
    form.resetFields();
    setModal({ type: 'create' });
  };

  const openEdit = (connector: Connector) => {
    form.setFieldsValue({
      connector_number: connector.connector_number,
      standard: connector.standard,
      max_power_kw: Number(connector.max_power_kw),
    });
    setModal({ type: 'edit', connector });
  };

  const openState = (connector: Connector) => {
    stateForm.setFieldsValue({ operational_status: connector.operational_status });
    setModal({ type: 'state', connector });
  };

  const closeModal = () => {
    form.resetFields();
    stateForm.resetFields();
    setModal(null);
  };

  const handleCreateOrEdit = async () => {
    try {
      const values = await form.validateFields();
      if (modal?.type === 'create') {
        await createMutation.mutateAsync({ stationId, ...values });
        message.success('Connecteur créé');
      } else if (modal?.type === 'edit') {
        await updateMutation.mutateAsync({ stationId, connectorId: modal.connector.id, ...values });
        message.success('Connecteur modifié');
      }
      closeModal();
    } catch (err: unknown) {
      if (isValidationError(err)) return;
      message.error(extractErrorMessage(err));
    }
  };

  const handleStateChange = async () => {
    if (modal?.type !== 'state') return;
    try {
      const values = await stateForm.validateFields();
      await stateMutation.mutateAsync({
        stationId,
        connectorId: modal.connector.id,
        operational_status: values.operational_status,
      });
      message.success('État mis à jour');
      closeModal();
    } catch (err: unknown) {
      if (isValidationError(err)) return;
      message.error(extractErrorMessage(err));
    }
  };

  const handleDelete = async (connector: Connector) => {
    try {
      await deleteMutation.mutateAsync({ stationId, connectorId: connector.id });
      message.success('Connecteur supprimé');
    } catch (err: unknown) {
      message.error(extractErrorMessage(err));
    }
  };

  const handleToggleAvailability = async (connector: Connector) => {
    const next = connector.administrative_status === 'enabled' ? 'disabled' : 'enabled';
    try {
      await availabilityMutation.mutateAsync({
        stationId,
        connectorId: connector.id,
        administrative_status: next,
      });
      message.success('Disponibilité mise à jour');
    } catch (err: unknown) {
      message.error(extractErrorMessage(err));
    }
  };

  const columns = [
    {
      title: 'N°',
      dataIndex: 'connector_number',
      key: 'connector_number',
      width: 60,
    },
    {
      title: 'Standard',
      dataIndex: 'standard',
      key: 'standard',
      render: (value: string) => STANDARD_LABELS[value] ?? value,
    },
    {
      title: 'Courant',
      dataIndex: 'current_type',
      key: 'current_type',
      render: (value: string) => CURRENT_TYPE_LABELS[value] ?? value,
    },
    {
      title: 'Puissance max',
      dataIndex: 'max_power_kw',
      key: 'max_power_kw',
      render: (value: string) => `${value} kW`,
    },
    {
      title: 'État opérationnel',
      dataIndex: 'operational_status',
      key: 'operational_status',
      render: (value: string) => (
        <Tag color={OP_COLORS[value] ?? 'default'}>{OP_STATUS_LABELS[value] ?? value}</Tag>
      ),
    },
    {
      title: 'Disponibilité',
      dataIndex: 'administrative_status',
      key: 'administrative_status',
      render: (value: string) => (
        <Tag color={ADMIN_COLORS[value] ?? 'default'}>
          {CONNECTOR_ADMIN_STATUS_LABELS[value] ?? value}
        </Tag>
      ),
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: unknown, connector: Connector) => (
        <Space wrap>
          {canUpdate && isCommissioning && (
            <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(connector)}>
              Modifier
            </Button>
          )}
          {canStateUpdate && !isDecommissioned && !isOcppConnected && (
            <Button size="small" icon={<ThunderboltOutlined />} onClick={() => openState(connector)}>
              État
            </Button>
          )}
          {canAvailabilityUpdate && !isDecommissioned && (
            <Popconfirm
              title={
                connector.administrative_status === 'enabled'
                  ? 'Désactiver ce connecteur ?'
                  : 'Activer ce connecteur ?'
              }
              okText={connector.administrative_status === 'enabled' ? 'Désactiver' : 'Activer'}
              cancelText="Annuler"
              onConfirm={() => handleToggleAvailability(connector)}
            >
              <Button size="small" icon={<PoweroffOutlined />}>
                {connector.administrative_status === 'enabled' ? 'Désactiver' : 'Activer'}
              </Button>
            </Popconfirm>
          )}
          {canDelete && isCommissioning && (
            <Popconfirm
              title="Supprimer ce connecteur ?"
              description="Le connecteur sera archivé (suppression douce), pas détruit définitivement."
              okText="Supprimer"
              cancelText="Annuler"
              okButtonProps={{ danger: true }}
              onConfirm={() => handleDelete(connector)}
            >
              <Button size="small" danger icon={<DeleteOutlined />}>
                Supprimer
              </Button>
            </Popconfirm>
          )}
        </Space>
      ),
    },
  ];

  return (
    <section className="station-connectors-section">
      <Card
        className="station-connectors-card"
        title="Connecteurs"
        extra={
          canCreate && isCommissioning ? (
            <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
              Nouveau connecteur
            </Button>
          ) : null
        }
      >
        {!isCommissioning && (
          <Alert
            className="station-connectors-alert"
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            message="La configuration physique des connecteurs (ajout, modification, suppression) n'est possible que lorsque la borne est en mise en service."
          />
        )}
        {isOcppConnected && (
          <Alert
            className="station-connectors-alert"
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            message="L'état opérationnel des connecteurs est géré automatiquement via OCPP tant que la borne est connectée."
          />
        )}
        <Table
          className="station-connectors-table"
          rowKey="id"
          loading={isLoading}
          columns={columns}
          dataSource={connectors ?? []}
          pagination={false}
          locale={{ emptyText: 'Aucun connecteur' }}
        />
      </Card>

      <Modal
        open={modal?.type === 'create' || modal?.type === 'edit'}
        title={modal?.type === 'edit' ? 'Modifier le connecteur' : 'Nouveau connecteur'}
        onCancel={closeModal}
        onOk={handleCreateOrEdit}
        confirmLoading={createMutation.isPending || updateMutation.isPending}
        destroyOnClose
      >
        <Form form={form} layout="vertical">
          <Form.Item
            name="connector_number"
            label="Numéro de connecteur"
            rules={[{ required: true, message: 'Requis' }]}
          >
            <InputNumber min={1} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item
            name="standard"
            label="Standard"
            rules={[{ required: true, message: 'Requis' }]}
          >
            <Select options={Object.entries(STANDARD_LABELS).map(([value, label]) => ({ value, label }))} />
          </Form.Item>
          <Form.Item
            name="max_power_kw"
            label={`Puissance max (kW) — ne doit pas dépasser ${stationPowerKw} kW`}
            rules={[{ required: true, message: 'Requis' }]}
          >
            <InputNumber min={0.1} step={0.1} style={{ width: '100%' }} />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        open={modal?.type === 'state'}
        title="Changer l'état opérationnel"
        onCancel={closeModal}
        onOk={handleStateChange}
        confirmLoading={stateMutation.isPending}
        destroyOnClose
      >
        <Form form={stateForm} layout="vertical">
          <Form.Item
            name="operational_status"
            label="Nouvel état"
            rules={[{ required: true, message: 'Requis' }]}
          >
            <Select options={Object.entries(OP_STATUS_LABELS).map(([value, label]) => ({ value, label }))} />
          </Form.Item>
        </Form>
      </Modal>
    </section>
  );
}
