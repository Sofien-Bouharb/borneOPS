import { useState } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { Form, Input, Button, Alert, Card, Typography } from 'antd';
import { LockOutlined } from '@ant-design/icons';
import apiClient from '../api/client';

const { Title, Text } = Typography;

interface ResetPasswordFormValues {
  password: string;
  password_confirmation: string;
}

/**
 * Reset-password page. Reached via the link in ResetPasswordNotification
 * (backend/app/Notifications/ResetPasswordNotification.php), which embeds
 * `token` and `email` as query parameters — see Section 7.4 of the Module 1
 * report. A successful reset also invalidates every other active session
 * for the account (Section 6.9), so we send the user back to /login rather
 * than trying to log them in directly here.
 */
export default function ResetPasswordPage() {
  const [form] = Form.useForm<ResetPasswordFormValues>();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const missingParams = !token || !email;

  const onFinish = async (values: ResetPasswordFormValues) => {
    setLoading(true);
    setError(null);
    try {
      await apiClient.post('/reset-password', {
        token,
        email,
        password: values.password,
        password_confirmation: values.password_confirmation,
      });

      navigate('/login', {
        replace: true,
        state: {
          notice: 'Your password has been reset. Please sign in with your new password.',
        },
      });
    } catch (err: any) {
      const status = err?.response?.status;
      if (status === 429) {
        setError('Too many attempts. Please wait a few minutes and try again.');
      } else if (status === 422) {
        const message =
          err?.response?.data?.message ??
          'This reset link is invalid or has expired. Please request a new one.';
        setError(message);
      } else {
        setError('Something went wrong. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ display: 'flex', justifyContent: 'center', paddingTop: 96 }}>
      <Card style={{ width: 400 }}>
        <Title level={3} style={{ marginBottom: 4 }}>
          Set a new password
        </Title>
        <Text type="secondary">
          Choose a new password for <strong>{email || 'your account'}</strong>.
        </Text>

        {missingParams ? (
          <Alert
            style={{ marginTop: 24 }}
            type="error"
            showIcon
            message="Invalid reset link"
            description="This link is missing required information. Please request a new password reset."
          />
        ) : (
          <Form
            form={form}
            layout="vertical"
            onFinish={onFinish}
            style={{ marginTop: 24 }}
            requiredMark={false}
          >
            {error && (
              <Alert
                style={{ marginBottom: 16 }}
                type="error"
                showIcon
                message={error}
                closable
                onClose={() => setError(null)}
              />
            )}

            <Form.Item
              name="password"
              label="New password"
              rules={[
                { required: true, message: 'Please enter a new password.' },
                { min: 8, message: 'Password must be at least 8 characters.' },
              ]}
              hasFeedback
            >
              <Input.Password prefix={<LockOutlined />} placeholder="New password" autoFocus />
            </Form.Item>

            <Form.Item
              name="password_confirmation"
              label="Confirm new password"
              dependencies={['password']}
              hasFeedback
              rules={[
                { required: true, message: 'Please confirm your new password.' },
                ({ getFieldValue }) => ({
                  validator(_, value) {
                    if (!value || getFieldValue('password') === value) {
                      return Promise.resolve();
                    }
                    return Promise.reject(new Error('Passwords do not match.'));
                  },
                }),
              ]}
            >
              <Input.Password prefix={<LockOutlined />} placeholder="Confirm new password" />
            </Form.Item>

            <Form.Item>
              <Button type="primary" htmlType="submit" loading={loading} block>
                Reset password
              </Button>
            </Form.Item>
          </Form>
        )}

        <div style={{ marginTop: 16, textAlign: 'center' }}>
          <Link to="/login">Back to login</Link>
        </div>
      </Card>
    </div>
  );
}
