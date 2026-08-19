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
                err.response?.data?.message ?? 'Code invalide ou expiré.',
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
                        <span className="brand__descriptor">Pilotage du réseau de recharge</span>
                    </span>
                </div>

                <div className="auth-context__content">
                    <p className="auth-context__eyebrow">Protection du compte</p>
                    <h1 className="auth-context__title">
                        Sécurisez les accès des équipes chargées de l’infrastructure.
                    </h1>
                    <p className="auth-context__copy">
                        Votre rôle peut avoir un impact sur l’infrastructure de recharge en service. L’authentification à deux facteurs
                        ajoute un contrôle essentiel à chaque connexion.
                    </p>
                </div>

                <div className="auth-context__footer">
                    <span className="auth-context__status" aria-hidden="true" />
                    Configuration de sécurité requise
                </div>
            </aside>

            <main className="auth-main">
                <div className="auth-panel auth-panel--wide">
                    <div className="auth-heading">
                        <p className="section-eyebrow">Authentification à deux facteurs</p>
                        <Title level={2}>Connectez votre application d’authentification</Title>
                        <Text className="auth-heading__copy">
                            Scannez le code, puis confirmez la configuration avec le code à 6 chiffres affiché
                            par votre application d’authentification.
                        </Text>
                    </div>

                    <div className="qr-setup">
                        <div className="qr-frame">
                            <QRCodeSVG
                                value={otpauthUrl}
                                size={188}
                                title="Code QR d’inscription à l’application d’authentification BorneOPS"
                            />
                        </div>

                        <div className="setup-steps">
                            <div className="setup-step">
                                <span className="setup-step__number">1</span>
                                <div>
                                    <span className="setup-step__title">Scannez le code QR</span>
                                    <p className="setup-step__copy">
                                        Utilisez votre application d’authentification approuvée pour ajouter
                                        ce compte.
                                    </p>
                                </div>
                            </div>

                            <div className="setup-step">
                                <span className="setup-step__number">2</span>
                                <div>
                                    <span className="setup-step__title">
                                        Ou saisissez la clé de configuration
                                    </span>
                                    <p className="setup-step__copy">
                                        Utilisez cette clé si votre appareil ne peut pas scanner le code.
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
                            Code de confirmation
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
                            Confirmer et activer l’A2F
                        </Button>

                        <div className="security-note">
                            <SafetyCertificateOutlined aria-hidden="true" />
                            <span>
                                Gardez la clé de configuration privée. Elle donne accès à vos
                                codes d’authentification.
                            </span>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}
