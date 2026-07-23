// resources/js/pages/DashboardPage.tsx
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { List, Tag, Button, Card, Typography, Popconfirm, message } from 'antd';
import {
    DeploymentUnitOutlined,
    DesktopOutlined,
    LaptopOutlined,
    LogoutOutlined,
    SafetyCertificateOutlined,
    UserOutlined,
} from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { fetchSessions, revokeSession } from '../api/sessions';

const { Text } = Typography;

export default function DashboardPage() {
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
            message.success('Session revoked.');
            queryClient.invalidateQueries({ queryKey: ['sessions'] });
        },
        onError: () => {
            message.error('Could not revoke that session. It may already be gone.');
            queryClient.invalidateQueries({ queryKey: ['sessions'] });
        },
    });

    const handleLogoutEverywhere = async () => {
        await logoutAll();
        navigate('/login', { replace: true });
    };

    return (
        <div className="ops-shell">
            <header className="ops-header">
                <div className="ops-header__inner">
                    <div className="brand" aria-label="BorneOPS">
                        <span className="brand__mark" aria-hidden="true">
                            <DeploymentUnitOutlined />
                        </span>
                        <span>
                            <span className="brand__name">BorneOPS</span>
                            <span className="brand__descriptor">Charge network control</span>
                        </span>
                    </div>
                    <div className="ops-header__context">
                        <SafetyCertificateOutlined aria-hidden="true" />
                        Authorized operator workspace
                    </div>
                </div>
            </header>

            <main className="ops-main">
                <div className="dashboard-heading">
                    <div>
                        <p className="section-eyebrow">Access administration</p>
                        <h1>Account &amp; sessions</h1>
                        <p>Review operator access and revoke sessions you do not recognize.</p>
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
                        <div className="role-list" aria-label="Assigned roles">
                            {user?.roles?.map((role) => (
                                <Tag className="role-tag" key={role.id}>
                                    {role.name}
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
                                Active sessions
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
                            Couldn’t load your active sessions. Try refreshing the page.
                        </Text>
                    )}

                    <List
                        dataSource={sessions ?? []}
                        locale={{ emptyText: 'No active sessions found.' }}
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
                                                  title="Revoke this session?"
                                                  description="That device will be signed out immediately."
                                                  okText="Revoke"
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
                                                      aria-label="Revoke this device session"
                                                  >
                                                      Revoke access
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
                                            {session.is_current ? 'This device' : 'Signed-in device'}
                                            {session.is_current && (
                                                <Tag className="current-tag">Current session</Tag>
                                            )}
                                        </div>
                                        <div className="session-time">
                                            Signed in{' '}
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
                        <span className="danger-zone__title">End all operator sessions</span>
                        <span className="danger-zone__copy">
                            Sign this account out on every device, including this one.
                        </span>
                    </div>
                    <Popconfirm
                        title="Log out everywhere?"
                        description="This will end every active session on every device, including this one."
                        okText="Log out everywhere"
                        okButtonProps={{ danger: true }}
                        onConfirm={handleLogoutEverywhere}
                    >
                        <Button danger icon={<LogoutOutlined />}>
                            Log out everywhere
                        </Button>
                    </Popconfirm>
                </div>
            </main>
        </div>
    );
}
