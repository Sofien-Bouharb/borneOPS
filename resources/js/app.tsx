import '../css/app.css';
import './bootstrap';
import './leaflet-setup';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ConfigProvider, theme } from 'antd';
import Root from './Root';

const queryClient = new QueryClient();
const fontFamily =
    'Inter, Aptos, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
const opsPalette = {
    backgroundBase: '#151310',
    backgroundElevated: '#211D18',
    backgroundRaised: '#29231D',
    border: '#786958',
    borderSubtle: '#4A4137',
    primary: '#F7C948',
    primaryHover: '#FFD866',
    primaryActive: '#DDAE2C',
    text: '#F5F1E8',
    textSecondary: '#B8AFA3',
};

const container = document.getElementById('app')!;
const root = createRoot(container);
root.render(
    <QueryClientProvider client={queryClient}>
        <ConfigProvider
            theme={{
                algorithm: theme.darkAlgorithm,
                token: {
                    colorPrimary: opsPalette.primary,
                    colorPrimaryHover: opsPalette.primaryHover,
                    colorPrimaryActive: opsPalette.primaryActive,
                    colorLink: opsPalette.primary,
                    colorLinkHover: opsPalette.primaryHover,
                    colorLinkActive: opsPalette.primaryActive,
                    colorInfo: '#4C9BD3',
                    colorSuccess: '#55C8AE',
                    colorWarning: '#E9A23B',
                    colorError: '#FF6B7A',
                    colorText: opsPalette.text,
                    colorTextHeading: opsPalette.text,
                    colorTextLabel: '#D8D0C5',
                    colorTextSecondary: opsPalette.textSecondary,
                    colorTextDescription: '#A69C8F',
                    colorTextPlaceholder: '#968C7F',
                    colorTextLightSolid: '#1A160E',
                    colorBgBase: opsPalette.backgroundBase,
                    colorBgLayout: opsPalette.backgroundBase,
                    colorBgContainer: opsPalette.backgroundElevated,
                    colorBgElevated: opsPalette.backgroundRaised,
                    colorBgSpotlight: '#302820',
                    colorBorder: opsPalette.border,
                    colorBorderSecondary: opsPalette.borderSubtle,
                    colorFillAlter: '#28221C',
                    colorFillSecondary: 'rgba(247, 201, 72, 0.09)',
                    controlItemBgActive: 'rgba(247, 201, 72, 0.16)',
                    controlItemBgActiveHover: 'rgba(247, 201, 72, 0.22)',
                    controlOutline: 'rgba(247, 201, 72, 0.34)',
                    borderRadius: 8,
                    borderRadiusLG: 12,
                    controlHeight: 42,
                    fontFamily,
                    fontSize: 15,
                    lineHeight: 1.5,
                    boxShadowSecondary: '0 16px 44px rgba(0, 0, 0, 0.34)',
                },
                components: {
                    Button: {
                        fontWeight: 650,
                        primaryColor: '#1A160E',
                        primaryShadow: '0 3px 10px rgba(247, 201, 72, 0.2)',
                    },
                    Card: {
                        headerFontSize: 16,
                        headerBg: 'transparent',
                        paddingLG: 24,
                    },
                    Form: {
                        labelColor: '#D8D0C5',
                        labelFontSize: 14,
                        itemMarginBottom: 20,
                    },
                    Input: {
                        activeBorderColor: opsPalette.primary,
                        hoverBorderColor: opsPalette.primaryHover,
                        activeShadow: '0 0 0 3px rgba(247, 201, 72, 0.16)',
                    },
                    Layout: {
                        bodyBg: opsPalette.backgroundBase,
                        headerBg: '#1A1713',
                        headerColor: opsPalette.text,
                        siderBg: '#1A1713',
                        lightSiderBg: '#1A1713',
                    },
                    Menu: {
                        itemBg: '#1A1713',
                        itemColor: opsPalette.textSecondary,
                        itemHoverBg: 'rgba(247, 201, 72, 0.08)',
                        itemHoverColor: opsPalette.primaryHover,
                        itemSelectedBg: 'rgba(247, 201, 72, 0.16)',
                        itemSelectedColor: opsPalette.primary,
                        itemActiveBg: 'rgba(247, 201, 72, 0.12)',
                    },
                    Table: {
                        headerBg: '#29231D',
                        headerColor: '#D8D0C5',
                        rowHoverBg: 'rgba(247, 201, 72, 0.06)',
                        borderColor: opsPalette.borderSubtle,
                    },
                    Typography: {
                        titleMarginBottom: '0.45em',
                        titleMarginTop: '0',
                    },
                },
            }}
        >
            <BrowserRouter>
                <Root />
            </BrowserRouter>
        </ConfigProvider>
    </QueryClientProvider>,
);
