// resources/js/components/AppShell.tsx
import { ReactNode, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { Layout, Menu, Drawer, Button, Dropdown, Grid, Typography } from 'antd';
import type { MenuProps } from 'antd';
import {
    DeploymentUnitOutlined,
    RadarChartOutlined,
    ThunderboltOutlined,
    ApartmentOutlined,
    EnvironmentOutlined,
    UserOutlined,
    MenuOutlined,
    LogoutOutlined,
    DownOutlined,
    SafetyCertificateOutlined,
} from '@ant-design/icons';
import { useAuth } from '../auth/AuthContext';

const { Sider, Header, Content } = Layout;
const { useBreakpoint } = Grid;
const { Text } = Typography;

interface NavItem {
    key: string;
    label: string;
    icon: ReactNode;
    permission: string | null;
}

const NAV_ITEMS: NavItem[] = [
    { key: '/dashboard', label: 'Supervision', icon: <RadarChartOutlined />, permission: 'supervision.view' },
    { key: '/stations', label: 'Bornes', icon: <ThunderboltOutlined />, permission: 'charging_stations.view' },
    { key: '/organizations', label: 'Organisations', icon: <ApartmentOutlined />, permission: 'organizations.view' },
    { key: '/sites', label: 'Sites', icon: <EnvironmentOutlined />, permission: 'sites.view' },
];

export default function AppShell({ children }: { children: ReactNode }) {
    const { user, logout } = useAuth();
    const location = useLocation();
    const navigate = useNavigate();
    const screens = useBreakpoint();
    const isMobile = !screens.lg;
    const [drawerOpen, setDrawerOpen] = useState(false);

    const visibleNavItems = NAV_ITEMS.filter(
        (item) => item.permission === null || user?.permissions?.includes(item.permission)
    );

    const selectedKey =
        visibleNavItems.find((item) => location.pathname.startsWith(item.key))?.key ?? '/dashboard';

    const menuItems: MenuProps['items'] = visibleNavItems.map((item) => ({
        key: item.key,
        icon: item.icon,
        label: item.label,
    }));

    function handleMenuClick({ key }: { key: string }) {
        navigate(key);
        setDrawerOpen(false);
    }

    async function handleLogout() {
        await logout();
        navigate('/login', { replace: true });
    }

    const userMenuItems: MenuProps['items'] = [
        {
            key: 'account',
            icon: <UserOutlined />,
            label: 'Mon compte',
            onClick: () => navigate('/account'),
        },
        {
            key: 'logout',
            icon: <LogoutOutlined />,
            label: 'Déconnexion',
            danger: true,
            onClick: handleLogout,
        },
    ];

    const brand = (
        <div className="brand" aria-label="BorneOPS">
            <span className="brand__mark" aria-hidden="true">
                <DeploymentUnitOutlined />
            </span>
            <span>
                <span className="brand__name">BorneOPS</span>
                <span className="brand__descriptor">Charge network control</span>
            </span>
        </div>
    );

    const navigationMenu = (
        <Menu
            mode="inline"
            selectedKeys={[selectedKey]}
            items={menuItems}
            onClick={handleMenuClick}
            style={{ borderInlineEnd: 'none' }}
        />
    );

    return (
        <Layout style={{ minHeight: '100vh' }}>
            {!isMobile && (
                <Sider width={240} theme="light" style={{ borderInlineEnd: '1px solid #D7E0E7' }}>
                    <div style={{ padding: '20px 16px' }}>{brand}</div>
                    {navigationMenu}
                </Sider>
            )}

            {isMobile && (
                <Drawer
                    title={brand}
                    placement="left"
                    onClose={() => setDrawerOpen(false)}
                    open={drawerOpen}
                    styles={{ body: { padding: 0 } }}
                >
                    {navigationMenu}
                </Drawer>
            )}

            <Layout>
                <Header
                    style={{
                        background: '#fff',
                        borderBottom: '1px solid #D7E0E7',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '0 20px',
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        {isMobile && (
                            <Button
                                type="text"
                                icon={<MenuOutlined />}
                                onClick={() => setDrawerOpen(true)}
                                aria-label="Open navigation menu"
                            />
                        )}
                        {isMobile && brand}
                        {!isMobile && (
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: '#12629C' }}>
                                <SafetyCertificateOutlined aria-hidden="true" />
                                <Text type="secondary">Authorized operator workspace</Text>
                            </div>
                        )}
                    </div>

                    <Dropdown menu={{ items: userMenuItems }} trigger={['click']}>
                        <Button type="text" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <UserOutlined />
                            <span>{user?.name}</span>
                            <DownOutlined style={{ fontSize: 10 }} />
                        </Button>
                    </Dropdown>
                </Header>

                <Content style={{ background: '#F3F6F8' }}>{children}</Content>
            </Layout>
        </Layout>
    );
}
