// resources/js/pages/LoginPage.tsx
import { useState } from 'react';
import { Form, Input, Button, Alert, Typography } from 'antd';
import {
    DeploymentUnitOutlined,
    LockOutlined,
    MailOutlined,
    SafetyCertificateOutlined,
} from '@ant-design/icons';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import TwoFactorSetupPage from './TwoFactorSetupPage';
import TwoFactorChallengePage from './TwoFactorChallengePage';
import { useLocation } from 'react-router-dom';

const { Title, Text } = Typography;


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
                    <p className="auth-context__eyebrow">Infrastructure operations</p>
                    <h1 className="auth-context__title">
                        The operational view of your charging network.
                    </h1>
                    <p className="auth-context__copy">
                        Supervise connected stations, respond to incidents, and protect
                        network availability from one controlled workspace.
                    </p>
                </div>

                <div className="auth-context__footer">
                    <span className="auth-context__status" aria-hidden="true" />
                    Secure operator access
                </div>
            </aside>

            <main className="auth-main">
                <div className="auth-panel">
                    <div className="auth-heading">
                        <p className="section-eyebrow">Operator access</p>
                        <Title level={2}>Sign in</Title>
                        <Text className="auth-heading__copy">
                            Use your organization credentials to access BorneOPS.
                        </Text>
                    </div>

                    {error && (
                        <Alert
                            className="auth-alert"
                            type="error"
                            message={error}
                            showIcon
                        />
                    )}
                    {notice && (
                        <Alert
                            className="auth-alert"
                            type="success"
                            message={notice}
                            showIcon
                        />
                    )}
                    <Form
                        className="auth-form"
                        layout="vertical"
                        onFinish={onFinish}
                        disabled={submitting}
                        requiredMark={false}
                    >
                        <Form.Item
                            label="Work email"
                            name="email"
                            rules={[
                                { required: true, message: 'Please enter your email' },
                                { type: 'email', message: 'Please enter a valid email' },
                            ]}
                        >
                            <Input
                                prefix={<MailOutlined aria-hidden="true" />}
                                autoComplete="email"
                                placeholder="operator@company.com"
                                autoFocus
                            />
                        </Form.Item>

                        <Form.Item
                            label="Password"
                            name="password"
                            rules={[{ required: true, message: 'Please enter your password' }]}
                        >
                            <Input.Password
                                prefix={<LockOutlined aria-hidden="true" />}
                                autoComplete="current-password"
                                placeholder="Enter your password"
                            />
                        </Form.Item>

                        <Form.Item className="auth-form__action">
                            <Button type="primary" htmlType="submit" block loading={submitting}>
                                Sign in to operations
                            </Button>
                        </Form.Item>
                        <div className="auth-panel__footer">
                            <Link to="/forgot-password">Forgot your password?</Link>
                        </div>
                    </Form>

                    <div className="security-note">
                        <SafetyCertificateOutlined aria-hidden="true" />
                        <span>
                            Access is restricted to authorized personnel and may be audited.
                        </span>
                    </div>
                </div>
            </main>
        </div>
    );
}
