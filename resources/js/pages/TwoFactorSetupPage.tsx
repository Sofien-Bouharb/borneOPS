// resources/js/pages/TwoFactorSetupPage.tsx
import { useState } from 'react';
import { Button, Alert, Typography, Input } from 'antd';
import { QRCodeSVG } from 'qrcode.react';
import { useAuth } from '../auth/AuthContext';
import api from '../api/client';

const { Title, Text, Paragraph } = Typography;

interface TwoFactorSetupPageProps {
    enrollmentId: string;
    secret: string;
    otpauthUrl: string;
    onEnrolled: (accessToken: string, user: any) => void;
}

export default function TwoFactorSetupPage({
    enrollmentId,
    secret,
    otpauthUrl,
    onEnrolled,
}: TwoFactorSetupPageProps) {
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    async function handleConfirm() {
        setError(null);
        setSubmitting(true);

        try {
            const response = await api.post('/login/setup-2fa', {
                enrollment_id: enrollmentId,
                code,
            });

            onEnrolled(response.data.access_token, response.data.user);
        } catch (err: any) {
            setError(
                err.response?.data?.message ?? 'Invalid or expired code.',
            );
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div style={{ maxWidth: 400, margin: '80px auto', textAlign: 'center' }}>
            <Title level={3}>Set up two-factor authentication</Title>

            <Paragraph>
                Your role requires two-factor authentication. Scan this QR code
                with an authenticator app (Google Authenticator, Authy, etc.),
                then enter the 6-digit code it shows.
            </Paragraph>

            <div style={{ margin: '24px 0' }}>
                <QRCodeSVG value={otpauthUrl} size={200} />
            </div>

            <Paragraph>
                <Text type="secondary">Can't scan? Enter this key manually:</Text>
                <br />
                <Text code copyable>
                    {secret}
                </Text>
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
                onPressEnter={handleConfirm}
            />

            <Button
                type="primary"
                block
                loading={submitting}
                onClick={handleConfirm}
            >
                Confirm and enable 2FA
            </Button>
        </div>
    );
}
