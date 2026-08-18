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
    TeamOutlined,
    MenuOutlined,
    LogoutOutlined,
    DownOutlined,
    SafetyCertificateOutlined,
    PlayCircleOutlined,
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
    { key: '/charging-sessions', label: 'Sessions de recharge', icon: <PlayCircleOutlined />, permission: 'charging_sessions.view' },
    { key: '/organizations', label: 'Organisations', icon: <ApartmentOutlined />, permission: 'organizations.view' },
    { key: '/sites', label: 'Sites', icon: <EnvironmentOutlined />, permission: 'sites.view' },
    { key: '/users', label: 'Utilisateurs', icon: <TeamOutlined />, permission: 'users.view' },
    { key: '/rfid-badges', label: 'Badges RFID', icon: <SafetyCertificateOutlined />, permission: 'rfid_badges.view' },
    { key: '/my-badges', label: 'Mes badges', icon: <SafetyCertificateOutlined />, permission: 'my_badges_only' },

];

export default function AppShell({ children }: { children: ReactNode }) {
    const { user, logout } = useAuth();
    const location = useLocation();
    const navigate = useNavigate();
    const screens = useBreakpoint();
    const isMobile = !screens.lg;
    const [drawerOpen, setDrawerOpen] = useState(false);

    const isClient = user?.roles?.includes('Client') ?? false;
    const visibleNavItems = NAV_ITEMS.filter((item) => {
        if (item.permission === 'my_badges_only') return isClient;
        if (item.key === '/rfid-badges' && isClient) return false;
        return item.permission === null || user?.permissions?.includes(item.permission);
    });

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
            className="ops-app-menu"
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
                <Sider className="ops-app-sider" width={240} theme="dark">
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
                    className="ops-app-header"
                    style={{
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
                            <div className="ops-header__trust">
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

                <Content className="ops-app-content">{children}</Content>
            </Layout>
        </Layout>
    );
}
