// resources/js/pages/AccountPage.tsx
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { List, Tag, Button, Card, Typography, Popconfirm, message } from 'antd';
import { DesktopOutlined, LaptopOutlined, LogoutOutlined, UserOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { fetchSessions, revokeSession } from '../api/sessions';
import AppShell from '../components/AppShell';

const { Text } = Typography;

export default function AccountPage() {
    const { user, logoutAll } = useAuth();
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const {
        data: sessions,
        isLoading,
        isError,
    } = useQuery({
        queryKey: ['sessions'],
        queryFn: fetchSessions,
    });

    const revokeMutation = useMutation({
        mutationFn: revokeSession,
        onSuccess: () => {
            message.success('Session révoquée.');
            queryClient.invalidateQueries({ queryKey: ['sessions'] });
        },
        onError: () => {
            message.error('Impossible de révoquer cette session. Elle a peut-être déjà expiré.');
            queryClient.invalidateQueries({ queryKey: ['sessions'] });
        },
    });

    const handleLogoutEverywhere = async () => {
        await logoutAll();
        navigate('/login', { replace: true });
    };

    return (
        <AppShell>
            <div style={{ padding: 24 }}>
                <div className="dashboard-heading">
                    <div>
                        <p className="section-eyebrow">Administration des accès</p>
                        <h1>Compte et sessions</h1>
                        <p>Vérifiez les accès des opérateurs et révoquez les sessions que vous ne reconnaissez pas.</p>
                    </div>
                </div>

                <Card className="account-card">
                    <div className="account-card__body">
                        <div className="account-identity">
                            <div className="account-avatar" aria-hidden="true">
                                <UserOutlined />
                            </div>
                            <div>
                                <span className="account-name">{user?.name}</span>
                                <Text type="secondary">{user?.email}</Text>
                            </div>
                        </div>
                        <div className="role-list" aria-label="Rôles attribués">
                            {user?.roles?.map((role) => (
                                <Tag className="role-tag" key={role}>
                                    {role}
                                </Tag>
                            ))}
                        </div>
                    </div>
                </Card>

                <Card
                    className="sessions-card"
                    title={
                        <div className="sessions-title">
                            <span className="sessions-title__label">
                                <DesktopOutlined aria-hidden="true" />
                                Sessions actives
                            </span>
                            {!isLoading && !isError && (
                                <span className="sessions-title__count">
                                    {sessions?.length ?? 0}{' '}
                                    {(sessions?.length ?? 0) === 1 ? 'session' : 'sessions'}
                                </span>
                            )}
                        </div>
                    }
                    loading={isLoading}
                >
                    {isError && (
                        <Text type="danger" role="alert">
                            Impossible de charger vos sessions actives. Veuillez actualiser la page.
                        </Text>
                    )}

                    <List
                        dataSource={sessions ?? []}
                        locale={{ emptyText: 'Aucune session active trouvée.' }}
                        renderItem={(session) => (
                            <List.Item
                                className="session-item"
                                key={session.id}
                                actions={
                                    session.is_current
                                        ? []
                                        : [
                                              <Popconfirm
                                                  key="revoke"
                                                  title="Révoquer cette session ?"
                                                  description="Cet appareil sera déconnecté immédiatement."
                                                  okText="Révoquer"
                                                  okButtonProps={{ danger: true }}
                                                  onConfirm={() => revokeMutation.mutate(session.id)}
                                              >
                                                  <Button
                                                      danger
                                                      size="small"
                                                      loading={
                                                          revokeMutation.isPending &&
                                                          revokeMutation.variables === session.id
                                                      }
                                                      aria-label="Révoquer la session de cet appareil"
                                                  >
                                                      Révoquer l’accès
                                                  </Button>
                                              </Popconfirm>,
                                          ]
                                }
                            >
                                <div className="session-meta">
                                    <div
                                        className={`session-icon${session.is_current ? ' session-icon--current' : ''}`}
                                        aria-hidden="true"
                                    >
                                        <LaptopOutlined />
                                    </div>
                                    <div>
                                        <div className="session-name">
                                            {session.is_current ? 'Cet appareil' : 'Appareil connecté'}
                                            {session.is_current && (
                                                <Tag className="current-tag">Session actuelle</Tag>
                                            )}
                                        </div>
                                        <div className="session-time">
                                            Connecté le{' '}
                                            <time dateTime={session.created_at}>
                                                {new Date(session.created_at).toLocaleString()}
                                            </time>
                                        </div>
                                    </div>
                                </div>
                            </List.Item>
                        )}
                    />
                </Card>

                <div className="danger-zone">
                    <div>
                        <span className="danger-zone__title">Mettre fin à toutes les sessions opérateur</span>
                        <span className="danger-zone__copy">
                            Déconnectez ce compte de tous les appareils, y compris celui-ci.
                        </span>
                    </div>
                    <Popconfirm
                        title="Se déconnecter de tous les appareils ?"
                        description="Cette action mettra fin à toutes les sessions actives sur tous les appareils, y compris celui-ci."
                        okText="Se déconnecter de tous les appareils"
                        okButtonProps={{ danger: true }}
                        onConfirm={handleLogoutEverywhere}
                    >
                        <Button danger icon={<LogoutOutlined />}>
                            Se déconnecter de tous les appareils
                        </Button>
                    </Popconfirm>
                </div>
            </div>
        </AppShell>
    );
}
