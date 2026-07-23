// resources/js/pages/DashboardPage.tsx
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { List, Tag, Button, Card, Typography, Popconfirm, message, Space } from 'antd';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { fetchSessions, revokeSession } from '../api/sessions';

const { Title, Text } = Typography;

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
        <div style={{ maxWidth: 640, margin: '48px auto', padding: '0 16px' }}>
            <Card>
                <Title level={3} style={{ marginBottom: 0 }}>
                    {user?.name}
                </Title>
                <Text type="secondary">{user?.email}</Text>
                <div style={{ marginTop: 8 }}>
                    {user?.roles?.map((role) => (
                        <Tag key={role.id} color="blue">
                            {role.name}
                        </Tag>
                    ))}
                </div>
            </Card>

            <Card title="Active sessions" style={{ marginTop: 24 }} loading={isLoading}>
                {isError && (
                    <Text type="danger">
                        Couldn't load your active sessions. Try refreshing the page.
                    </Text>
                )}

                <List
                    dataSource={sessions ?? []}
                    locale={{ emptyText: 'No active sessions found.' }}
                    renderItem={(session) => (
                        <List.Item
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
                                              >
                                                  Revoke
                                              </Button>
                                          </Popconfirm>,
                                      ]
                            }
                        >
                            <List.Item.Meta
                                title={
                                    <Space>
                                        {session.is_current ? 'This device' : 'Other device'}
                                        {session.is_current && <Tag color="green">Current</Tag>}
                                    </Space>
                                }
                                description={`Signed in ${new Date(session.created_at).toLocaleString()}`}
                            />
                        </List.Item>
                    )}
                />
            </Card>

            <Popconfirm
                title="Log out everywhere?"
                description="This will end every active session on every device, including this one."
                okText="Log out everywhere"
                okButtonProps={{ danger: true }}
                onConfirm={handleLogoutEverywhere}
            >
                <Button danger style={{ marginTop: 24 }} block>
                    Log out everywhere
                </Button>
            </Popconfirm>
        </div>
    );
}
