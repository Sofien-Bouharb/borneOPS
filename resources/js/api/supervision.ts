import api from './client';
import { useQuery } from '@tanstack/react-query';

// --- Types ---

export interface SupervisionStationSite {
  id: number;
  name: string;
  address: string | null;
  organization: {
    id: number;
    name: string;
  } | null;
}

export interface SupervisionStation {
  id: number;
  name: string;
  reference: string;
  serial_number: string;
  model: string;
  manufacturer: string;
  latitude: string;
  longitude: string;
  administrative_status: string;
  operational_status: string;
  connection_status: 'connected' | 'disconnected';
  last_heartbeat_at: string | null;
  disconnected_at: string | null;
  declared_connector_count: number;
  actual_connector_count?: number;
  connector_count_matches?: boolean;
  site?: SupervisionStationSite;
}

export interface SupervisionKpis {
  stations_total: number;
  stations_by_administrative_status: Record<string, number>;
  stations_by_operational_status: Record<string, number>;
  stations_by_connection_status: Record<string, number>;
  stations_missing_coordinates: number;
  stations_with_connector_mismatch: number;
  connectors_total: number;
  connectors_by_operational_status: Record<string, number>;
  connectors_by_administrative_status: Record<string, number>;
}

export interface SupervisionDashboard {
  kpis: SupervisionKpis;
  stations: SupervisionStation[];
}

// --- API functions ---

async function fetchSupervisionDashboard(): Promise<SupervisionDashboard> {
  const { data } = await api.get('/supervision/dashboard');
  return data.data;
}

// --- React Query hooks ---

export function useSupervisionDashboard() {
  return useQuery({
    queryKey: ['supervision-dashboard'],
    queryFn: fetchSupervisionDashboard,
  });
}
