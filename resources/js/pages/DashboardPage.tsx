// resources/js/pages/DashboardPage.tsx
import { DeploymentUnitOutlined, RadarChartOutlined, SafetyCertificateOutlined } from '@ant-design/icons';
import { Card, Typography } from 'antd';

const { Text } = Typography;

export default function DashboardPage() {
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
            </main>
        </div>
    );
}
