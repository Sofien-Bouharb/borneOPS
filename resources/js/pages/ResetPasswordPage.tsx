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
          notice: 'Votre mot de passe a été réinitialisé. Veuillez vous connecter avec votre nouveau mot de passe.',
        },
      });
    } catch (err: any) {
      const status = err?.response?.status;
      if (status === 429) {
        setError('Trop de tentatives. Veuillez patienter quelques minutes avant de réessayer.');
      } else if (status === 422) {
        const message =
          err?.response?.data?.message ??
          'Ce lien de réinitialisation est invalide ou a expiré. Veuillez en demander un nouveau.';
        setError(message);
      } else {
        setError('Une erreur est survenue. Veuillez réessayer.');
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
            <span className="brand__descriptor">Pilotage du réseau de recharge</span>
          </span>
        </div>

        <div className="auth-context__content">
          <p className="auth-context__eyebrow">Sécurité des identifiants</p>
          <h1 className="auth-context__title">
            Gardez le contrôle des accès opérationnels.
          </h1>
          <p className="auth-context__copy">
            Définissez un mot de passe robuste et unique pour votre identité opérateur. Les
            sessions existantes seront invalidées après la réinitialisation.
          </p>
        </div>

        <div className="auth-context__footer">
          <span className="auth-context__status" aria-hidden="true" />
          Mise à jour sécurisée du mot de passe
        </div>
      </aside>

      <main className="auth-main">
        <div className="auth-panel">
          <div className="auth-heading">
            <p className="section-eyebrow">Réinitialisation du mot de passe</p>
            <Title level={2}>Définissez un nouveau mot de passe</Title>
            <Text className="auth-heading__copy">
              Choisissez un nouveau mot de passe pour <strong>{email || 'votre compte'}</strong>.
            </Text>
          </div>

          {missingParams ? (
            <Alert
              type="error"
              showIcon
              message="Lien de réinitialisation invalide"
              description="Il manque des informations requises dans ce lien. Veuillez demander une nouvelle réinitialisation de mot de passe."
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
                label="Nouveau mot de passe"
                rules={[
                  { required: true, message: 'Veuillez saisir un nouveau mot de passe.' },
                  { min: 8, message: 'Le mot de passe doit comporter au moins 8 caractères.' },
                ]}
                hasFeedback
              >
                <Input.Password
                  prefix={<LockOutlined aria-hidden="true" />}
                  placeholder="Nouveau mot de passe"
                  autoComplete="new-password"
                  autoFocus
                />
              </Form.Item>

              <Form.Item
                name="password_confirmation"
                label="Confirmez le nouveau mot de passe"
                dependencies={['password']}
                hasFeedback
                rules={[
                  { required: true, message: 'Veuillez confirmer votre nouveau mot de passe.' },
                  ({ getFieldValue }) => ({
                    validator(_, value) {
                      if (!value || getFieldValue('password') === value) {
                        return Promise.resolve();
                      }
                      return Promise.reject(new Error('Les mots de passe ne correspondent pas.'));
                    },
                  }),
                ]}
              >
                <Input.Password
                  prefix={<LockOutlined aria-hidden="true" />}
                  placeholder="Confirmez le nouveau mot de passe"
                  autoComplete="new-password"
                />
              </Form.Item>

              <Form.Item className="auth-form__action">
                <Button type="primary" htmlType="submit" loading={loading} block>
                  Mettre à jour le mot de passe
                </Button>
              </Form.Item>

              <div className="security-note">
                <SafetyCertificateOutlined aria-hidden="true" />
                <span>Toutes les sessions existantes seront déconnectées après cette modification.</span>
              </div>
            </Form>
          )}

          <div className="auth-panel__footer">
            <Link to="/login">
              <ArrowLeftOutlined aria-hidden="true" /> Retour à la connexion
            </Link>
          </div>
        </div>
      </main>
    </div>
  );
}
