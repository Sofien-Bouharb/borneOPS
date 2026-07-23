// resources/js/api/sessions.ts
import api from './client';

export interface SessionSummary {
    id: string;
    created_at: string;
    is_current: boolean;
}

export async function fetchSessions(): Promise<SessionSummary[]> {
    const { data } = await api.get<{ sessions: SessionSummary[] }>('/sessions');
    return data.sessions;
}

export async function revokeSession(sessionId: string): Promise<void> {
    await api.delete(`/sessions/${sessionId}`);
}
