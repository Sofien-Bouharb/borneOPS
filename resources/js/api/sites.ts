import api from './client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { Organization } from './organizations';

export interface Site {
  id: number;
  name: string;
  address: string;
  organization_id: number;
  latitude: string | null;
  longitude: string | null;
  organization?: Organization;
  created_at: string;
  updated_at: string;
}

async function fetchSites(): Promise<Site[]> {
  const { data } = await api.get('/sites');
  return data.data;
}

async function fetchSite(id: number): Promise<Site> {
  const { data } = await api.get(`/sites/${id}`);
  return data.data;
}

async function createSite(payload: Record<string, unknown>): Promise<Site> {
  const { data } = await api.post('/sites', payload);
  return data.data;
}

async function updateSite(id: number, payload: Record<string, unknown>): Promise<Site> {
  const { data } = await api.patch(`/sites/${id}`, payload);
  return data.data;
}

export function useSitesList() {
  return useQuery({ queryKey: ['sites-list'], queryFn: fetchSites });
}

export function useSite(id: number) {
  return useQuery({ queryKey: ['site', id], queryFn: () => fetchSite(id) });
}

export function useCreateSite() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: createSite,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sites-list'] });
      qc.invalidateQueries({ queryKey: ['sites'] });
    },
  });
}

export function useUpdateSite() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, ...payload }: { id: number } & Record<string, unknown>) => updateSite(id, payload),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['sites-list'] });
      qc.invalidateQueries({ queryKey: ['site', vars.id] });
      qc.invalidateQueries({ queryKey: ['sites'] });
    },
  });
}
