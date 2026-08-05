// resources/js/pages/DashboardPage.tsx
import { useEffect } from 'react';
import { RadarChartOutlined } from '@ant-design/icons';
import { Card, Typography } from 'antd';
import echo from '../echo';
import AppShell from '../components/AppShell';

const { Text } = Typography;

export default function DashboardPage() {
    useEffect(() => {
        echo.private('supervision').listen('.station.updated', (e: unknown) => {
            console.log('Received event:', e);
        });

        console.log('Subscribed to private-supervision channel.');

        return () => {
            echo.leave('supervision');
        };
    }, []);

    return (
        <AppShell>
            <div style={{ padding: 24 }}>
                <div className="dashboard-heading">
                    <div>
                        <p className="section-eyebrow">Real-time supervision</p>
                        <h1>Supervision dashboard</h1>
                        <p>The live station map, status overview, and real-time updates are being built in Module 4.</p>
                    </div>
                </div>

                <Card className="account-card">
                    <div className="account-card__body">
                        <div className="account-identity">
                            <div className="account-avatar" aria-hidden="true">
                                <RadarChartOutlined />
                            </div>
                            <div>
                                <span className="account-name">Coming soon</span>
                                <Text type="secondary">
                                    This page will show the interactive station map, live status counts, and
                                    automatic WebSocket updates.
                                </Text>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </AppShell>
    );
}
