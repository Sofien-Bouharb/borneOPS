// resources/js/pages/LoginPage.tsx
import { useState } from 'react';
import { Form, Input, Button, Alert, Typography } from 'antd';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import TwoFactorSetupPage from './TwoFactorSetupPage';
import TwoFactorChallengePage from './TwoFactorChallengePage';
import { useLocation } from 'react-router-dom';

const { Title } = Typography;


interface LoginFormValues {
    email: string;
    password: string;
}

interface EnrollmentData {
    enrollmentId: string;
    secret: string;
    otpauthUrl: string;
}

export default function LoginPage() {
    const { login, completeLogin } = useAuth();
    const navigate = useNavigate();
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [enrollment, setEnrollment] = useState<EnrollmentData | null>(null);
    const [challengeId, setChallengeId] = useState<string | null>(null);
    const location = useLocation();
    const notice = (location.state as { notice?: string } | null)?.notice;

    async function onFinish(values: LoginFormValues) {
        setError(null);
        setSubmitting(true);

        try {
            const result = await login(values.email, values.password);

            if (result.status === 'success') {
                navigate('/dashboard');
            } else if (result.status === 'requires_2fa') {
                setChallengeId(result.challenge_id!);
            } else if (result.status === 'requires_2fa_setup') {
                setEnrollment({
                    enrollmentId: result.enrollment_id!,
                    secret: result.secret!,
                    otpauthUrl: result.otpauth_url!,
                });
            }
        } catch (err: any) {
            setError(
                err.response?.data?.message ?? 'Unable to log in. Please try again.',
            );
        } finally {
            setSubmitting(false);
        }
    }

    if (enrollment) {
        return (
            <TwoFactorSetupPage
                enrollmentId={enrollment.enrollmentId}
                secret={enrollment.secret}
                otpauthUrl={enrollment.otpauthUrl}
                onEnrolled={(accessToken, user) => {
                    completeLogin(accessToken, user);
                    navigate('/dashboard');
                }}
            />
        );
    }

    if (challengeId) {
        return (
            <TwoFactorChallengePage
                challengeId={challengeId}
                onVerified={(accessToken, user) => {
                    completeLogin(accessToken, user);
                    navigate('/dashboard');
                }}
            />
        );
    }

    return (
        <div style={{ maxWidth: 360, margin: '80px auto' }}>
            <Title level={3}>Sign in to BorneOPS</Title>

            {error && (
                <Alert
                    type="error"
                    message={error}
                    style={{ marginBottom: 16 }}
                    showIcon
                />
            )}
            {notice && <Alert type="success" message={notice} />}
            <Form layout="vertical" onFinish={onFinish} disabled={submitting}>
                <Form.Item
                    label="Email"
                    name="email"
                    rules={[
                        { required: true, message: 'Please enter your email' },
                        { type: 'email', message: 'Please enter a valid email' },
                    ]}
                >
                    <Input autoComplete="email" />
                </Form.Item>

                <Form.Item
                    label="Password"
                    name="password"
                    rules={[{ required: true, message: 'Please enter your password' }]}
                >
                    <Input.Password autoComplete="current-password" />
                </Form.Item>

                <Form.Item>
                    <Button type="primary" htmlType="submit" block loading={submitting}>
                        Sign in
                    </Button>
                </Form.Item>
                <div style={{ textAlign: 'center', marginTop: 8 }}>
                  <Link to="/forgot-password">Forgot password?</Link>
                </div>
            </Form>
        </div>
    );
}
