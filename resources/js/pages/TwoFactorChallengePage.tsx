// resources/js/pages/TwoFactorChallengePage.tsx
import { useState } from 'react';
import { Button, Alert, Typography, Input } from 'antd';
import {
    DeploymentUnitOutlined,
    SafetyCertificateOutlined,
} from '@ant-design/icons';
import api from '../api/client';

const { Title, Text } = Typography;

interface TwoFactorChallengePageProps {
    challengeId: string;
    onVerified: (accessToken: string, user: any) => void;
}

export default function TwoFactorChallengePage({
    challengeId,
    onVerified,
}: TwoFactorChallengePageProps) {
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    async function handleVerify() {
        setError(null);
        setSubmitting(true);

        try {
            const response = await api.post('/login/verify-2fa', {
                challenge_id: challengeId,
                code,
            });

            onVerified(response.data.access_token, response.data.user);
        } catch (err: any) {
            setError(
                err.response?.data?.message ?? 'Invalid or expired code.',
            );
        } finally {
            setSubmitting(false);
        }
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
                    <p className="auth-context__eyebrow">Identity verification</p>
                    <h1 className="auth-context__title">
                        A second check protects critical operations.
                    </h1>
                    <p className="auth-context__copy">
                        Multi-factor authentication helps ensure that station controls and
                        network data remain available only to verified operators.
                    </p>
                </div>

                <div className="auth-context__footer">
                    <span className="auth-context__status" aria-hidden="true" />
                    Verification step 2 of 2
                </div>
            </aside>

            <main className="auth-main">
                <div className="auth-panel">
                    <div className="auth-heading">
                        <p className="section-eyebrow">Two-factor authentication</p>
                        <Title level={2}>Verify your identity</Title>
                        <Text className="auth-heading__copy">
                            Enter the 6-digit code from your authenticator app to continue.
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

                    <label className="setup-step__title" htmlFor="authentication-code">
                        Authentication code
                    </label>
                    <Input
                        id="authentication-code"
                        className="otp-input"
                        placeholder="000000"
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                        maxLength={6}
                        onPressEnter={handleVerify}
                        autoComplete="one-time-code"
                        inputMode="numeric"
                        autoFocus
                    />

                    <Button
                        className="otp-submit"
                        type="primary"
                        block
                        loading={submitting}
                        onClick={handleVerify}
                    >
                        Verify and continue
                    </Button>

                    <div className="security-note">
                        <SafetyCertificateOutlined aria-hidden="true" />
                        <span>Codes are time-limited and can only be used once.</span>
                    </div>
                </div>
            </main>
        </div>
    );
}
