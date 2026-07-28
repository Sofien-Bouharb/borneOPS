import api from './client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

// --- Types ---

export interface ChargingStation {
  id: number;
  name: string;
  reference: string;
  serial_number: string;
  model: string;
  manufacturer: string;
  address: string | null;
  latitude: string;
  longitude: string;
  firmware_version: string | null;
  ocpp_version: string;
  ocpp_identifier: string | null;
  declared_connector_count: number;
  power_kw: string;
  operational_status: string;
  administrative_status: string;
  site_id: number | null;
  created_at: string;
  updated_at: string;
  site?: Site;
}

export interface Site {
  id: number;
  name: string;
  address: string;
  organization_id: number;
}

export interface Organization {
  id: number;
  name: string;
  type: string;
}

export interface HistoryEntry {
  id: number;
  charging_station_id: number;
  event_type: string;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  reason: string | null;
  comment: string | null;
  source: string;
  performed_by: { id: number; name: string; email: string } | null;
  created_at: string;
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

export interface StationFilters {
  page?: number;
  administrative_status?: string;
  operational_status?: string;
  manufacturer?: string;
  site_id?: number;
  organization_id?: number;
  search?: string;
}

// --- API functions ---

async function fetchStations(filters: StationFilters = {}): Promise<PaginatedResponse<ChargingStation>> {
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v !== undefined && v !== ''),
  );
  const { data } = await api.get('/charging-stations', { params });
  return data;
}

async function fetchStation(id: number): Promise<ChargingStation> {
  const { data } = await api.get(`/charging-stations/${id}`);
  return data.data;
}

async function createStation(payload: Record<string, unknown>): Promise<ChargingStation> {
  const { data } = await api.post('/charging-stations', payload);
  return data.data;
}

async function updateStation(id: number, payload: Record<string, unknown>): Promise<ChargingStation> {
  const { data } = await api.patch(`/charging-stations/${id}`, payload);
  return data.data;
}

async function fetchStationHistory(id: number, page = 1): Promise<PaginatedResponse<HistoryEntry>> {
  const { data } = await api.get(`/charging-stations/${id}/history`, { params: { page } });
  return data;
}

async function fetchOrganizations(): Promise<Organization[]> {
  const { data } = await api.get('/organizations');
  return data.data;
}

async function fetchSites(): Promise<Site[]> {
  const { data } = await api.get('/sites');
  return data.data;
}

// --- React Query hooks ---

export function useStations(filters: StationFilters = {}) {
  return useQuery({
    queryKey: ['stations', filters],
    queryFn: () => fetchStations(filters),
  });
}

export function useStation(id: number) {
  return useQuery({
    queryKey: ['station', id],
    queryFn: () => fetchStation(id),
  });
}

export function useStationHistory(id: number, page = 1) {
  return useQuery({
    queryKey: ['station-history', id, page],
    queryFn: () => fetchStationHistory(id, page),
  });
}

export function useOrganizations() {
  return useQuery({
    queryKey: ['organizations'],
    queryFn: fetchOrganizations,
  });
}

export function useSites() {
  return useQuery({
    queryKey: ['sites'],
    queryFn: fetchSites,
  });
}

export function useCreateStation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: createStation,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['stations'] }),
  });
}

export function useUpdateStation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, ...payload }: { id: number } & Record<string, unknown>) => updateStation(id, payload),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['stations'] });
      qc.invalidateQueries({ queryKey: ['station', vars.id] });
    },
  });
}

export function useStationAction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, action, payload }: { id: number; action: string; payload: Record<string, unknown> }) => {
      const { data } = await api.patch(`/charging-stations/${id}/${action}`, payload);
      return data.data as ChargingStation;
    },
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['stations'] });
      qc.invalidateQueries({ queryKey: ['station', vars.id] });
      qc.invalidateQueries({ queryKey: ['station-history', vars.id] });
    },
  });
}
