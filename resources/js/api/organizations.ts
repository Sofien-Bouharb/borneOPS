import api from './client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

export interface Organization {
  id: number;
  name: string;
  type: string;
  contact_email: string | null;
  contact_phone: string | null;
  address: string | null;
  created_at: string;
  updated_at: string;
}

async function fetchOrganizations(): Promise<Organization[]> {
  const { data } = await api.get('/organizations');
  return data.data;
}

async function fetchOrganization(id: number): Promise<Organization> {
  const { data } = await api.get(`/organizations/${id}`);
  return data.data;
}

async function createOrganization(payload: Record<string, unknown>): Promise<Organization> {
  const { data } = await api.post('/organizations', payload);
  return data.data;
}

async function updateOrganization(id: number, payload: Record<string, unknown>): Promise<Organization> {
  const { data } = await api.patch(`/organizations/${id}`, payload);
  return data.data;
}

export function useOrganizationsList() {
  return useQuery({ queryKey: ['organizations-list'], queryFn: fetchOrganizations });
}

export function useOrganization(id: number) {
  return useQuery({ queryKey: ['organization', id], queryFn: () => fetchOrganization(id) });
}

export function useCreateOrganization() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: createOrganization,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['organizations-list'] });
      qc.invalidateQueries({ queryKey: ['organizations'] });
    },
  });
}

export function useUpdateOrganization() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, ...payload }: { id: number } & Record<string, unknown>) => updateOrganization(id, payload),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['organizations-list'] });
      qc.invalidateQueries({ queryKey: ['organization', vars.id] });
      qc.invalidateQueries({ queryKey: ['organizations'] });
    },
  });
}
