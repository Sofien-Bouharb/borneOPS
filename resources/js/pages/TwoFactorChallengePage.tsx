// resources/js/pages/TwoFactorChallengePage.tsx
import { useState } from 'react';
import { Button, Alert, Typography, Input } from 'antd';
import api from '../api/client';

const { Title, Paragraph } = Typography;

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
        <div style={{ maxWidth: 360, margin: '80px auto', textAlign: 'center' }}>
            <Title level={3}>Enter your authentication code</Title>

            <Paragraph>
                Enter the 6-digit code from your authenticator app.
            </Paragraph>

            {error && (
                <Alert
                    type="error"
                    message={error}
                    style={{ marginBottom: 16 }}
                    showIcon
                />
            )}

            <Input
                placeholder="6-digit code"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                maxLength={6}
                style={{ marginBottom: 16 }}
                onPressEnter={handleVerify}
                autoFocus
            />

            <Button
                type="primary"
                block
                loading={submitting}
                onClick={handleVerify}
            >
                Verify
            </Button>
        </div>
    );
}
