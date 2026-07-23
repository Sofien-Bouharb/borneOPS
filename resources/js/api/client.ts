// resources/js/api/client.ts
import axios from 'axios';

let accessToken: string | null = null;

export function setAccessToken(token: string | null) {
    accessToken = token;
}

export function getAccessToken() {
    return accessToken;
}

const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
});

api.interceptors.request.use((config) => {
    if (accessToken) {
        config.headers.Authorization = `Bearer ${accessToken}`;
    }
    return config;
});

let refreshPromise: Promise<string | null> | null = null;

async function refreshAccessToken(): Promise<string | null> {
    try {
        const response = await axios.post(
            '/api/refresh',
            {},
            { withCredentials: true },
        );
        const newToken = response.data.access_token as string;
        setAccessToken(newToken);
        return newToken;
    } catch {
        setAccessToken(null);
        return null;
    }
}


const AUTH_ENDPOINTS = ['/login', '/login/verify-2fa', '/login/setup-2fa', '/refresh', '/forgot-password', '/reset-password'];

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;
        const isAuthEndpoint = AUTH_ENDPOINTS.some((path) =>
            originalRequest.url?.includes(path),
        );

        if (
            error.response?.status === 401 &&
            !originalRequest._retry &&
            !isAuthEndpoint
        ) {
            originalRequest._retry = true;

            if (!refreshPromise) {
                refreshPromise = refreshAccessToken().finally(() => {
                    refreshPromise = null;
                });
            }

            const newToken = await refreshPromise;

            if (newToken) {
                originalRequest.headers.Authorization = `Bearer ${newToken}`;
                return api(originalRequest);
            }
        }

        return Promise.reject(error);
    },
);

export default api;
