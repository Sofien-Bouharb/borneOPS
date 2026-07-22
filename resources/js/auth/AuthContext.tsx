// resources/js/auth/AuthContext.tsx
import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import api, { setAccessToken } from '../api/client';

interface User {
    id: number;
    name: string;
    email: string;
}

interface LoginResult {
    status: 'success' | 'requires_2fa' | 'requires_2fa_setup';
    challenge_id?: string;
    enrollment_id?: string;
    secret?: string;
    otpauth_url?: string;
}

interface AuthContextValue {
    user: User | null;
    loading: boolean;
    login: (email: string, password: string) => Promise<LoginResult>;
    completeLogin: (accessToken: string, user: User) => void;
    logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        // On first load, attempt a silent refresh — if the user has a valid
        // refresh_token cookie from a previous visit, this restores their
        // session without asking them to log in again.
        api.post('/refresh')
            .then((response) => {
                setAccessToken(response.data.access_token);
                return api.get('/me');
            })
            .then((response) => {
                setUser(response.data);
            })
            .catch(() => {
                setAccessToken(null);
                setUser(null);
            })
            .finally(() => {
                setLoading(false);
            });
    }, []);

    async function login(email: string, password: string): Promise<LoginResult> {
        const response = await api.post('/login', { email, password });
        const result = response.data as LoginResult;

        if (result.status === 'success') {
            setAccessToken((response.data as any).access_token);
            setUser((response.data as any).user);
        }

        return result;
    }

    function completeLogin(accessToken: string, loggedInUser: User) {
        setAccessToken(accessToken);
        setUser(loggedInUser);
    }

    async function logout() {
        await api.post('/logout').catch(() => {});
        setAccessToken(null);
        setUser(null);
    }

    return (
        <AuthContext.Provider value={{ user, loading, login, completeLogin, logout }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}
