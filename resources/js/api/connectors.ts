import api from './client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ChargingStation } from './stations';

// --- Types ---

export interface Connector {
  id: number;
  charging_station_id: number;
  connector_number: number;
  standard: string;
  current_type: string;
  max_power_kw: string;
  operational_status: string;
  administrative_status: string;
  created_at: string;
  updated_at: string;
  charging_station?: Pick<ChargingStation, 'id' | 'name' | 'reference'>;
}

export interface ConnectorListResponse {
  data: Connector[];
}

export interface ConnectorResponse {
  data: Connector;
}

// --- API functions ---

async function fetchConnectors(stationId: number): Promise<Connector[]> {
  const { data } = await api.get(`/charging-stations/${stationId}/connectors`);
  return data.data;
}

async function fetchConnector(stationId: number, connectorId: number): Promise<Connector> {
  const { data } = await api.get(`/charging-stations/${stationId}/connectors/${connectorId}`);
  return data.data;
}

async function createConnector(stationId: number, payload: Record<string, unknown>): Promise<Connector> {
  const { data } = await api.post(`/charging-stations/${stationId}/connectors`, payload);
  return data.data;
}

async function updateConnector(
  stationId: number,
  connectorId: number,
  payload: Record<string, unknown>,
): Promise<Connector> {
  const { data } = await api.patch(`/charging-stations/${stationId}/connectors/${connectorId}`, payload);
  return data.data;
}

async function deleteConnector(stationId: number, connectorId: number): Promise<void> {
  await api.delete(`/charging-stations/${stationId}/connectors/${connectorId}`);
}

async function updateConnectorState(
  stationId: number,
  connectorId: number,
  operational_status: string,
): Promise<Connector> {
  const { data } = await api.patch(`/charging-stations/${stationId}/connectors/${connectorId}/state`, {
    operational_status,
  });
  return data.data;
}

async function updateConnectorAvailability(
  stationId: number,
  connectorId: number,
  administrative_status: string,
): Promise<Connector> {
  const { data } = await api.patch(
    `/charging-stations/${stationId}/connectors/${connectorId}/availability`,
    { administrative_status },
  );
  return data.data;
}

// --- React Query hooks ---

export function useConnectors(stationId: number) {
  return useQuery({
    queryKey: ['connectors', stationId],
    queryFn: () => fetchConnectors(stationId),
    enabled: !!stationId,
  });
}

export function useConnector(stationId: number, connectorId: number) {
  return useQuery({
    queryKey: ['connector', stationId, connectorId],
    queryFn: () => fetchConnector(stationId, connectorId),
    enabled: !!stationId && !!connectorId,
  });
}

export function useCreateConnector() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ stationId, ...payload }: { stationId: number } & Record<string, unknown>) =>
      createConnector(stationId, payload),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['connectors', vars.stationId] });
      qc.invalidateQueries({ queryKey: ['station', vars.stationId] });
    },
  });
}

export function useUpdateConnector() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      stationId,
      connectorId,
      ...payload
    }: { stationId: number; connectorId: number } & Record<string, unknown>) =>
      updateConnector(stationId, connectorId, payload),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['connectors', vars.stationId] });
      qc.invalidateQueries({ queryKey: ['connector', vars.stationId, vars.connectorId] });
      qc.invalidateQueries({ queryKey: ['station', vars.stationId] });
    },
  });
}

export function useDeleteConnector() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ stationId, connectorId }: { stationId: number; connectorId: number }) =>
      deleteConnector(stationId, connectorId),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['connectors', vars.stationId] });
      qc.invalidateQueries({ queryKey: ['station', vars.stationId] });
    },
  });
}

export function useUpdateConnectorState() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      stationId,
      connectorId,
      operational_status,
    }: {
      stationId: number;
      connectorId: number;
      operational_status: string;
    }) => updateConnectorState(stationId, connectorId, operational_status),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['connectors', vars.stationId] });
      qc.invalidateQueries({ queryKey: ['connector', vars.stationId, vars.connectorId] });
      qc.invalidateQueries({ queryKey: ['station', vars.stationId] });
    },
  });
}

export function useUpdateConnectorAvailability() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      stationId,
      connectorId,
      administrative_status,
    }: {
      stationId: number;
      connectorId: number;
      administrative_status: string;
    }) => updateConnectorAvailability(stationId, connectorId, administrative_status),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['connectors', vars.stationId] });
      qc.invalidateQueries({ queryKey: ['connector', vars.stationId, vars.connectorId] });
      qc.invalidateQueries({ queryKey: ['station', vars.stationId] });
    },
  });
}
