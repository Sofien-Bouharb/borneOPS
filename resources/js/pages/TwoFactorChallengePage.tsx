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
                    <p className="auth-context__eyebrow">Vérification d’identité</p>
                    <h1 className="auth-context__title">
                        Une seconde vérification protège les opérations critiques.
                    </h1>
                    <p className="auth-context__copy">
                        L’authentification multifacteur garantit que les commandes des bornes et
                        les données du réseau restent accessibles uniquement aux opérateurs vérifiés.
                    </p>
                </div>

                <div className="auth-context__footer">
                    <span className="auth-context__status" aria-hidden="true" />
                    Étape de vérification 2 sur 2
                </div>
            </aside>

            <main className="auth-main">
                <div className="auth-panel">
                    <div className="auth-heading">
                        <p className="section-eyebrow">Authentification à deux facteurs</p>
                        <Title level={2}>Vérifiez votre identité</Title>
                        <Text className="auth-heading__copy">
                            Saisissez le code à 6 chiffres de votre application d’authentification pour continuer.
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
                        Code d’authentification
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
                        Vérifier et continuer
                    </Button>

                    <div className="security-note">
                        <SafetyCertificateOutlined aria-hidden="true" />
                        <span>Les codes ont une durée de validité limitée et ne peuvent être utilisés qu’une seule fois.</span>
                    </div>
                </div>
            </main>
        </div>
    );
}
