import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Form, Input, Button, Alert, Card, Typography } from 'antd';
import { MailOutlined } from '@ant-design/icons';
import apiClient from '../api/client';

const { Title, Text } = Typography;

interface ForgotPasswordFormValues {
  email: string;
}

/**
 * Forgot-password entry point.
 *
 * The backend's /forgot-password endpoint deliberately returns the same
 * generic success response whether or not the submitted email exists
 * (anti-enumeration — see Section 6.7 of the Module 1 report). This page
 * mirrors that on the frontend: we show the identical success message
 * regardless of the API result, and never reveal whether the account exists.
 */
export default function ForgotPasswordPage() {
  const [form] = Form.useForm<ForgotPasswordFormValues>();
  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const onFinish = async (values: ForgotPasswordFormValues) => {
    setLoading(true);
    setError(null);
    try {
      await apiClient.post('/forgot-password', { email: values.email });
      // Always show the generic confirmation, matching the backend's
      // anti-enumeration behavior. We do this on success AND on most
      // failure paths (see catch block) so the UI never leaks account
      // existence either.
      setSubmitted(true);
    } catch (err: any) {
      const status = err?.response?.status;
      if (status === 429) {
        setError('Too many attempts. Please wait a few minutes and try again.');
      } else if (status === 422) {
        setError('Please enter a valid email address.');
      } else {
        // Any other failure: still don't reveal whether the account
        // exists. Show the same generic confirmation as a success case
        // would, so the error surface can't be used to enumerate emails.
        setSubmitted(true);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ display: 'flex', justifyContent: 'center', paddingTop: 96 }}>
      <Card style={{ width: 400 }}>
        <Title level={3} style={{ marginBottom: 4 }}>
          Reset your password
        </Title>
        <Text type="secondary">
          Enter the email on your account and we'll send you a link to reset your password.
        </Text>

        {submitted ? (
          <Alert
            style={{ marginTop: 24 }}
            type="success"
            showIcon
            message="Check your email"
            description="If an account exists for that email address, we've sent a link to reset your password."
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
              name="email"
              label="Email"
              rules={[
                { required: true, message: 'Please enter your email address.' },
                { type: 'email', message: 'Please enter a valid email address.' },
              ]}
            >
              <Input prefix={<MailOutlined />} placeholder="you@example.com" autoFocus />
            </Form.Item>

            <Form.Item>
              <Button type="primary" htmlType="submit" loading={loading} block>
                Send reset link
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
