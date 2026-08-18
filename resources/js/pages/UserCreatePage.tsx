import { useNavigate } from 'react-router-dom';
import { Form, Input, Select, Button, Card, Typography, message, Space } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { useCreateUser, ASSIGNABLE_ROLES } from '../api/users';
import { useOrganizationsList } from '../api/organizations';
import { extractErrorMessage, translateBackendMessage } from '../utils/apiErrors';
import AppShell from '../components/AppShell';

const { Title } = Typography;

export default function UserCreatePage() {
  const navigate = useNavigate();
  const [form] = Form.useForm();
  const createMutation = useCreateUser();
  const { data: organizations } = useOrganizationsList();

  const selectedRole = Form.useWatch('role', form);
  const isClient = selectedRole === 'Client';

  const onFinish = async (values: Record<string, unknown>) => {
    try {
      const user = await createMutation.mutateAsync(values);
      message.success('Utilisateur créé avec succès. Un lien de configuration du mot de passe lui a été envoyé.');
      navigate(`/users/${user.id}`);
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
        <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/users')}>
          Retour à la liste
        </Button>
        <Title className="station-page-title station-page-title--form" level={3}>Nouvel utilisateur</Title>

        <Card className="station-form-card">
          <Form
            className="station-form"
            form={form}
            layout="vertical"
            onFinish={onFinish}
            onValuesChange={(changed) => {
              if (changed.role && changed.role !== 'Client') {
                form.setFieldValue('organization_ids', undefined);
              }
            }}
          >
            <Form.Item name="name" label="Nom" rules={[{ required: true, message: 'Requis' }]}>
              <Input />
            </Form.Item>
            <Form.Item name="email" label="Email" rules={[{ required: true, type: 'email', message: 'Email valide requis' }]}>
              <Input />
            </Form.Item>
            <Form.Item name="role" label="Rôle" help="Laisser vide pour créer un compte sans rôle attribué.">
              <Select
                allowClear
                options={ASSIGNABLE_ROLES.map((r) => ({ value: r, label: r }))}
              />
            </Form.Item>
            {isClient && (
              <Form.Item
                name="organization_ids"
                label="Organisation(s)"
                rules={[{ required: true, message: 'Au moins une organisation est requise pour un rôle Client' }]}
                help="Un utilisateur Client doit appartenir à au moins une organisation."
              >
                <Select
                  mode="multiple"
                  placeholder="Sélectionner une ou plusieurs organisations"
                  options={(organizations ?? []).map((o) => ({ value: o.id, label: o.name }))}
                />
              </Form.Item>
            )}

            <Form.Item className="station-form-actions">
              <Space>
                <Button onClick={() => navigate('/users')}>Annuler</Button>
                <Button type="primary" htmlType="submit" loading={createMutation.isPending}>
                  Créer
                </Button>
              </Space>
            </Form.Item>
          </Form>
        </Card>
      </main>
    </AppShell>
  );
}
