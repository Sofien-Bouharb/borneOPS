import '../css/app.css';
import './bootstrap';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ConfigProvider } from 'antd';
import Root from './Root';

const queryClient = new QueryClient();

const container = document.getElementById('app')!;
const root = createRoot(container);
root.render(
    <QueryClientProvider client={queryClient}>
        <ConfigProvider>
            <BrowserRouter>
                <Root />
            </BrowserRouter>
        </ConfigProvider>
    </QueryClientProvider>,
);
