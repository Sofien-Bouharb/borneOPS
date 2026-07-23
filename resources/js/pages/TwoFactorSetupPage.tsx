// resources/js/pages/TwoFactorSetupPage.tsx
import { useState } from 'react';
import { Button, Alert, Typography, Input } from 'antd';
import {
    DeploymentUnitOutlined,
    SafetyCertificateOutlined,
} from '@ant-design/icons';
import { QRCodeSVG } from 'qrcode.react';
import api from '../api/client';

const { Title, Text } = Typography;

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
                    <p className="auth-context__eyebrow">Account protection</p>
                    <h1 className="auth-context__title">
                        Secure access for infrastructure teams.
                    </h1>
                    <p className="auth-context__copy">
                        Your role can affect live charging infrastructure. Two-factor
                        authentication adds an essential control to every sign-in.
                    </p>
                </div>

                <div className="auth-context__footer">
                    <span className="auth-context__status" aria-hidden="true" />
                    Required security setup
                </div>
            </aside>

            <main className="auth-main">
                <div className="auth-panel auth-panel--wide">
                    <div className="auth-heading">
                        <p className="section-eyebrow">Two-factor authentication</p>
                        <Title level={2}>Connect your authenticator</Title>
                        <Text className="auth-heading__copy">
                            Scan the code, then confirm setup with the 6-digit code shown
                            by your authenticator app.
                        </Text>
                    </div>

                    <div className="qr-setup">
                        <div className="qr-frame">
                            <QRCodeSVG
                                value={otpauthUrl}
                                size={188}
                                title="BorneOPS authenticator enrollment QR code"
                            />
                        </div>

                        <div className="setup-steps">
                            <div className="setup-step">
                                <span className="setup-step__number">1</span>
                                <div>
                                    <span className="setup-step__title">Scan the QR code</span>
                                    <p className="setup-step__copy">
                                        Use your approved authenticator application to add
                                        this account.
                                    </p>
                                </div>
                            </div>

                            <div className="setup-step">
                                <span className="setup-step__number">2</span>
                                <div>
                                    <span className="setup-step__title">
                                        Or enter the setup key
                                    </span>
                                    <p className="setup-step__copy">
                                        Use this key if your device cannot scan the code.
                                    </p>
                                    <Text className="setup-secret" code copyable>
                                        {secret}
                                    </Text>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="setup-confirm">
                        {error && (
                            <Alert
                                className="auth-alert"
                                type="error"
                                message={error}
                                showIcon
                            />
                        )}

                        <label className="setup-step__title" htmlFor="setup-code">
                            Confirmation code
                        </label>
                        <Input
                            id="setup-code"
                            className="otp-input"
                            placeholder="000000"
                            value={code}
                            onChange={(e) => setCode(e.target.value)}
                            maxLength={6}
                            onPressEnter={handleConfirm}
                            autoComplete="one-time-code"
                            inputMode="numeric"
                            autoFocus
                        />

                        <Button
                            className="otp-submit"
                            type="primary"
                            block
                            loading={submitting}
                            onClick={handleConfirm}
                        >
                            Confirm and enable 2FA
                        </Button>

                        <div className="security-note">
                            <SafetyCertificateOutlined aria-hidden="true" />
                            <span>
                                Keep the setup key private. It grants access to your
                                authentication codes.
                            </span>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}
