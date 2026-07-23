import '../css/app.css';
import './bootstrap';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ConfigProvider, theme } from 'antd';
import Root from './Root';

const queryClient = new QueryClient();
const fontFamily =
    'Inter, Aptos, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

const container = document.getElementById('app')!;
const root = createRoot(container);
root.render(
    <QueryClientProvider client={queryClient}>
        <ConfigProvider
            theme={{
                algorithm: theme.defaultAlgorithm,
                token: {
                    colorPrimary: '#12629C',
                    colorInfo: '#12629C',
                    colorSuccess: '#157A6E',
                    colorWarning: '#B76B12',
                    colorError: '#B4233C',
                    colorText: '#172B3A',
                    colorTextSecondary: '#5B6F7F',
                    colorBgLayout: '#F3F6F8',
                    colorBgContainer: '#FFFFFF',
                    colorBorder: '#D7E0E7',
                    colorBorderSecondary: '#E7EDF1',
                    borderRadius: 8,
                    borderRadiusLG: 12,
                    controlHeight: 42,
                    fontFamily,
                    fontSize: 15,
                    lineHeight: 1.5,
                    boxShadowSecondary: '0 16px 44px rgba(11, 31, 51, 0.12)',
                },
                components: {
                    Button: {
                        fontWeight: 650,
                        primaryShadow: '0 2px 5px rgba(18, 98, 156, 0.22)',
                    },
                    Card: {
                        headerFontSize: 16,
                        paddingLG: 24,
                    },
                    Form: {
                        labelColor: '#2D4658',
                        labelFontSize: 14,
                        itemMarginBottom: 20,
                    },
                    Input: {
                        activeBorderColor: '#12629C',
                        hoverBorderColor: '#287BAF',
                        activeShadow: '0 0 0 3px rgba(18, 98, 156, 0.14)',
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
