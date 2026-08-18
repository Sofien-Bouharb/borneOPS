import api from './client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

// --- Types ---

export interface RfidBadgeUser {
  id: number;
  name: string;
  email: string;
}

export interface RfidBadge {
  id: number;
  identifier_hint: string;
  label: string | null;
  administrative_status: 'pending' | 'active' | 'blocked';
  expires_at: string | null;
  activated_at: string | null;
  blocked_at: string | null;
  user?: RfidBadgeUser;
  created_at: string;
  updated_at: string;
}

export interface RfidBadgeHistory {
  id: number;
  rfid_badge_id: number;
  event_type: string;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  source: string;
  performed_by?: RfidBadgeUser;
  created_at: string;
}

export interface RfidBadgeListResponse {
  data: RfidBadge[];
  meta: { current_page: number; last_page: number; total: number; per_page: number };
}

interface RfidBadgeFilters {
  page?: number;
  administrative_status?: string;
  user_id?: number;
}

// --- API functions ---

async function fetchBadges(filters: RfidBadgeFilters): Promise<RfidBadgeListResponse> {
  const { data } = await api.get('/rfid-badges', { params: filters });
  return data;
}

async function fetchBadge(id: number): Promise<RfidBadge> {
  const { data } = await api.get(`/rfid-badges/${id}`);
  return data.data;
}

async function fetchBadgeHistory(id: number, page = 1): Promise<{ data: RfidBadgeHistory[]; meta: { current_page: number; last_page: number; total: number } }> {
  const { data } = await api.get(`/rfid-badges/${id}/history`, { params: { page } });
  return data;
}

async function createBadge(payload: { user_id: number; identifier: string; label?: string; expires_at?: string }): Promise<RfidBadge> {
  const { data } = await api.post('/rfid-badges', payload);
  return data.data;
}

async function updateBadge(id: number, payload: { label?: string }): Promise<RfidBadge> {
  const { data } = await api.patch(`/rfid-badges/${id}`, payload);
  return data.data;
}

async function reassignBadge(id: number, user_id: number): Promise<RfidBadge> {
  const { data } = await api.patch(`/rfid-badges/${id}/reassign`, { user_id });
  return data.data;
}

async function activateBadge(id: number): Promise<RfidBadge> {
  const { data } = await api.patch(`/rfid-badges/${id}/activate`);
  return data.data;
}

async function blockBadge(id: number): Promise<RfidBadge> {
  const { data } = await api.patch(`/rfid-badges/${id}/block`);
  return data.data;
}

async function updateBadgeExpiration(id: number, expires_at: string | null): Promise<RfidBadge> {
  const { data } = await api.patch(`/rfid-badges/${id}/expiration`, { expires_at });
  return data.data;
}

// --- React Query hooks ---

export function useRfidBadges(filters: RfidBadgeFilters) {
  return useQuery({
    queryKey: ['rfid-badges', filters],
    queryFn: () => fetchBadges(filters),
  });
}

export function useRfidBadge(id: number) {
  return useQuery({
    queryKey: ['rfid-badge', id],
    queryFn: () => fetchBadge(id),
    enabled: !!id,
  });
}

export function useRfidBadgeHistory(id: number, page = 1) {
  return useQuery({
    queryKey: ['rfid-badge-history', id, page],
    queryFn: () => fetchBadgeHistory(id, page),
    enabled: !!id,
  });
}

function useInvalidateBadges() {
  const qc = useQueryClient();
  return (id?: number) => {
    qc.invalidateQueries({ queryKey: ['rfid-badges'] });
    if (id) {
      qc.invalidateQueries({ queryKey: ['rfid-badge', id] });
      qc.invalidateQueries({ queryKey: ['rfid-badge-history', id] });
    }
  };
}

export function useCreateRfidBadge() {
  const invalidate = useInvalidateBadges();
  return useMutation({
    mutationFn: (payload: { user_id: number; identifier: string; label?: string; expires_at?: string }) => createBadge(payload),
    onSuccess: () => invalidate(),
  });
}

export function useUpdateRfidBadge() {
  const invalidate = useInvalidateBadges();
  return useMutation({
    mutationFn: ({ id, ...payload }: { id: number; label?: string }) => updateBadge(id, payload),
    onSuccess: (_, vars) => invalidate(vars.id),
  });
}

export function useReassignRfidBadge() {
  const invalidate = useInvalidateBadges();
  return useMutation({
    mutationFn: ({ id, user_id }: { id: number; user_id: number }) => reassignBadge(id, user_id),
    onSuccess: (_, vars) => invalidate(vars.id),
  });
}

export function useActivateRfidBadge() {
  const invalidate = useInvalidateBadges();
  return useMutation({
    mutationFn: (id: number) => activateBadge(id),
    onSuccess: (_, id) => invalidate(id),
  });
}

export function useBlockRfidBadge() {
  const invalidate = useInvalidateBadges();
  return useMutation({
    mutationFn: (id: number) => blockBadge(id),
    onSuccess: (_, id) => invalidate(id),
  });
}

export function useUpdateRfidBadgeExpiration() {
  const invalidate = useInvalidateBadges();
  return useMutation({
    mutationFn: ({ id, expires_at }: { id: number; expires_at: string | null }) => updateBadgeExpiration(id, expires_at),
    onSuccess: (_, vars) => invalidate(vars.id),
  });
}
