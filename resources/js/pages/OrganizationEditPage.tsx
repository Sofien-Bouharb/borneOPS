import { useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Form, Input, Select, Button, Card, Typography, Spin, Alert, Space, message } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { useOrganization, useUpdateOrganization } from '../api/organizations';
import { extractErrorMessage, translateBackendMessage } from '../utils/apiErrors';
import AppShell from '../components/AppShell';

const { Title } = Typography;

export default function OrganizationEditPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const orgId = Number(id);
  const [form] = Form.useForm();
  const { data: org, isLoading } = useOrganization(orgId);
  const updateMutation = useUpdateOrganization();

  useEffect(() => {
    if (org) {
      form.setFieldsValue({
        name: org.name,
        type: org.type,
        contact_email: org.contact_email,
        contact_phone: org.contact_phone,
        address: org.address,
      });
    }
  }, [org, form]);

  if (isLoading) return <AppShell><div className="station-page-state"><Spin /></div></AppShell>;
  if (!org) return <AppShell><Alert className="station-page-error" type="error" message="Organisation introuvable" /></AppShell>;

  const onFinish = async (values: Record<string, unknown>) => {
    try {
      await updateMutation.mutateAsync({ id: orgId, ...values });
      message.success('Organisation mise à jour');
      navigate(`/organizations/${orgId}`);
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
        <Button className="station-back-button" type="link" icon={<ArrowLeftOutlined />} onClick={() => navigate(`/organizations/${orgId}`)}>
          Retour au détail
        </Button>
        <Title className="station-page-title station-page-title--form" level={3}>Modifier — {org.name}</Title>

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
                <Button onClick={() => navigate(`/organizations/${orgId}`)}>Annuler</Button>
                <Button type="primary" htmlType="submit" loading={updateMutation.isPending}>
                  Enregistrer
                </Button>
              </Space>
            </Form.Item>
          </Form>
        </Card>
      </main>
    </AppShell>
  );
}
