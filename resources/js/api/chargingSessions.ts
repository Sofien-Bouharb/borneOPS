import api from './client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

// --- Types ---

export interface ChargingSessionStationSummary {
  id: number;
  name: string;
  reference: string;
}

export interface ChargingSessionConnectorSummary {
  id: number;
  connector_number: number;
  standard: string;
}

export interface ChargingSessionCustomerSummary {
  id: number;
  name: string;
  email: string;
}

export interface ChargingSession {
  id: number;
  charging_station_id: number;
  connector_id: number;
  customer_user_id: number | null;
  status: 'pending' | 'active' | 'paused' | 'completed' | 'cancelled';
  reason_code: string | null;
  reason_detail: string | null;
  meter_start_wh: number | null;
  latest_meter_wh: number | null;
  meter_stop_wh: number | null;
  energy_consumed_wh: number | null;
  energy_consumed_kwh: number | null;
  total_price: string | null;
  currency: string | null;
  ocpp_transaction_id: string | null;
  started_at: string | null;
  paused_at: string | null;
  total_paused_seconds: number | null;
  completed_at: string | null;
  cancelled_at: string | null;
  duration_seconds: number | null;
  charging_seconds: number | null;
  created_at: string;
  updated_at: string;
  charging_station?: ChargingSessionStationSummary;
  connector?: ChargingSessionConnectorSummary;
  customer?: ChargingSessionCustomerSummary | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}

export interface ChargingSessionFilters {
  page?: number;
  per_page?: number;
  status?: string;
  charging_station_id?: number;
  connector_id?: number;
  customer_user_id?: number;
}

// --- API functions ---

async function fetchChargingSessions(filters: ChargingSessionFilters = {}): Promise<PaginatedResponse<ChargingSession>> {
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v !== undefined && v !== ''),
  );
  const { data } = await api.get('/charging-sessions', { params });
  return data;
}

async function fetchChargingSession(id: number): Promise<ChargingSession> {
  const { data } = await api.get(`/charging-sessions/${id}`);
  return data.data;
}

async function createChargingSession(payload: {
  charging_station_id: number;
  connector_id: number;
  customer_user_id?: number;
}): Promise<ChargingSession> {
  const { data } = await api.post('/charging-sessions', payload);
  return data.data;
}

// --- React Query hooks ---

export function useChargingSessions(filters: ChargingSessionFilters = {}) {
  return useQuery({
    queryKey: ['charging-sessions', filters],
    queryFn: () => fetchChargingSessions(filters),
  });
}

export function useChargingSession(id: number) {
  return useQuery({
    queryKey: ['charging-session', id],
    queryFn: () => fetchChargingSession(id),
  });
}

export function useCreateChargingSession() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: createChargingSession,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['charging-sessions'] }),
  });
}

/**
 * Covers start/pause/resume/end/cancel — all five are POST
 * /charging-sessions/{id}/{action} with an optional JSON body, and all five
 * return the updated ChargingSessionResource shape. Mirrors
 * useStationAction()'s generic-action pattern from Module 2.
 */
export function useChargingSessionAction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      action,
      payload,
    }: {
      id: number;
      action: 'start' | 'pause' | 'resume' | 'end' | 'cancel';
      payload?: Record<string, unknown>;
    }) => {
      const { data } = await api.post(`/charging-sessions/${id}/${action}`, payload ?? {});
      return data.data as ChargingSession;
    },
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['charging-sessions'] });
      qc.invalidateQueries({ queryKey: ['charging-session', vars.id] });
    },
  });
}
