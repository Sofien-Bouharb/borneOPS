import api from './client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { Organization } from './organizations';

// --- Types ---

export interface AppUser {
  id: number;
  name: string;
  email: string;
  account_status: string;
  roles: string[];
  organizations: Organization[];
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface PaginatedUsers {
  data: AppUser[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface UserFilters {
  page?: number;
  per_page?: number;
  account_status?: string;
  role?: string;
  organization_id?: number;
  search?: string;
}

export const ASSIGNABLE_ROLES = ['Opérateur', 'Technicien', 'Service Client', 'Client'];

// --- API functions ---

async function fetchUsers(filters: UserFilters = {}): Promise<PaginatedUsers> {
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v !== undefined && v !== ''),
  );
  const { data } = await api.get('/users', { params });
  return data;
}

async function fetchUser(id: number): Promise<AppUser> {
  const { data } = await api.get(`/users/${id}`);
  return data.data;
}

async function createUser(payload: Record<string, unknown>): Promise<AppUser> {
  const { data } = await api.post('/users', payload);
  return data.data;
}

async function updateAccountStatus(id: number, account_status: string): Promise<AppUser> {
  const { data } = await api.patch(`/users/${id}/account-status`, { account_status });
  return data.data;
}

async function assignRole(id: number, role: string): Promise<AppUser> {
  const { data } = await api.patch(`/users/${id}/role`, { role });
  return data.data;
}

async function syncOrganizations(id: number, organization_ids: number[]): Promise<AppUser> {
  const { data } = await api.patch(`/users/${id}/organizations`, { organization_ids });
  return data.data;
}

async function resendPasswordSetupLink(id: number): Promise<{ message: string }> {
  const { data } = await api.post(`/users/${id}/password-setup-link`);
  return data;
}

// --- React Query hooks ---

export function useUsers(filters: UserFilters = {}) {
  return useQuery({
    queryKey: ['users', filters],
    queryFn: () => fetchUsers(filters),
  });
}

export function useUser(id: number) {
  return useQuery({
    queryKey: ['user', id],
    queryFn: () => fetchUser(id),
  });
}

export function useCreateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: createUser,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}

export function useUpdateAccountStatus() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, account_status }: { id: number; account_status: string }) =>
      updateAccountStatus(id, account_status),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['users'] });
      qc.invalidateQueries({ queryKey: ['user', vars.id] });
    },
  });
}

export function useAssignRole() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, role }: { id: number; role: string }) => assignRole(id, role),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['users'] });
      qc.invalidateQueries({ queryKey: ['user', vars.id] });
    },
  });
}

export function useSyncOrganizations() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, organization_ids }: { id: number; organization_ids: number[] }) =>
      syncOrganizations(id, organization_ids),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['users'] });
      qc.invalidateQueries({ queryKey: ['user', vars.id] });
    },
  });
}

export function useResendPasswordSetupLink() {
  return useMutation({
    mutationFn: (id: number) => resendPasswordSetupLink(id),
  });
}
