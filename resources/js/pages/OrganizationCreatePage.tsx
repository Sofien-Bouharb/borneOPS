import { useNavigate } from 'react-router-dom';
import { Form, Input, Select, Button, Card, Typography, message, Space } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { useCreateOrganization } from '../api/organizations';
import { extractErrorMessage, translateBackendMessage } from '../utils/apiErrors';
import AppShell from '../components/AppShell';

const { Title } = Typography;

export default function OrganizationCreatePage() {
  const navigate = useNavigate();
  const [form] = Form.useForm();
  const createMutation = useCreateOrganization();

  const onFinish = async (values: Record<string, unknown>) => {
    try {
      const org = await createMutation.mutateAsync(values);
      message.success('Organisation créée avec succès');
      navigate(`/organizations/${org.id}`);
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
        <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate('/organizations')}>
          Retour à la liste
        </Button>
        <Title className="station-page-title station-page-title--form" level={3}>Nouvelle organisation</Title>

        <Card className="station-form-card">
          <Form className="station-form" form={form} layout="vertical" onFinish={onFinish}>
            <Form.Item name="name" label="Nom" rules={[{ required: true, message: 'Requis' }]}>
              <Input />
            </Form.Item>
            <Form.Item name="type" label="Type" rules={[{ required: true, message: 'Requis' }]}>
              <Select options={[
                { value: 'operator', label: 'Opérateur' },
                { value: 'client', label: 'Client' },
              ]} />
            </Form.Item>
            <Form.Item name="contact_email" label="Email de contact" rules={[{ type: 'email', message: 'Email invalide' }]}>
              <Input />
            </Form.Item>
            <Form.Item name="contact_phone" label="Téléphone">
              <Input />
            </Form.Item>
            <Form.Item name="address" label="Adresse">
              <Input.TextArea rows={2} />
            </Form.Item>

            <Form.Item className="station-form-actions">
              <Space>
                <Button onClick={() => navigate('/organizations')}>Annuler</Button>
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
