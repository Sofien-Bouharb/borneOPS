import { useState } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { Form, Input, Button, Alert, Typography } from 'antd';
import {
  ArrowLeftOutlined,
  DeploymentUnitOutlined,
  LockOutlined,
  SafetyCertificateOutlined,
} from '@ant-design/icons';
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
    <div className="auth-page">
      <aside className="auth-context">
        <div className="brand" aria-label="BorneOPS">
          <span className="brand__mark" aria-hidden="true">
            <DeploymentUnitOutlined />
          </span>
          <span>
            <span className="brand__name">BorneOPS</span>
            <span className="brand__descriptor">Charge network control</span>
          </span>
        </div>

        <div className="auth-context__content">
          <p className="auth-context__eyebrow">Credential security</p>
          <h1 className="auth-context__title">
            Keep operational access controlled.
          </h1>
          <p className="auth-context__copy">
            Set a strong, unique password for your operator identity. Existing
            sessions will be invalidated after the reset.
          </p>
        </div>

        <div className="auth-context__footer">
          <span className="auth-context__status" aria-hidden="true" />
          Secure password update
        </div>
      </aside>

      <main className="auth-main">
        <div className="auth-panel">
          <div className="auth-heading">
            <p className="section-eyebrow">Password reset</p>
            <Title level={2}>Set a new password</Title>
            <Text className="auth-heading__copy">
              Choose a new password for <strong>{email || 'your account'}</strong>.
            </Text>
          </div>

          {missingParams ? (
            <Alert
              type="error"
              showIcon
              message="Invalid reset link"
              description="This link is missing required information. Please request a new password reset."
            />
          ) : (
            <Form
              className="auth-form"
              form={form}
              layout="vertical"
              onFinish={onFinish}
              requiredMark={false}
            >
              {error && (
                <Alert
                  className="auth-alert"
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
                <Input.Password
                  prefix={<LockOutlined aria-hidden="true" />}
                  placeholder="New password"
                  autoComplete="new-password"
                  autoFocus
                />
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
                <Input.Password
                  prefix={<LockOutlined aria-hidden="true" />}
                  placeholder="Confirm new password"
                  autoComplete="new-password"
                />
              </Form.Item>

              <Form.Item className="auth-form__action">
                <Button type="primary" htmlType="submit" loading={loading} block>
                  Update password
                </Button>
              </Form.Item>

              <div className="security-note">
                <SafetyCertificateOutlined aria-hidden="true" />
                <span>All existing sessions will be signed out after this change.</span>
              </div>
            </Form>
          )}

          <div className="auth-panel__footer">
            <Link to="/login">
              <ArrowLeftOutlined aria-hidden="true" /> Back to sign in
            </Link>
          </div>
        </div>
      </main>
    </div>
  );
}
