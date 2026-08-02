// Field name translations (English DB column → French label)
export const FIELD_LABELS: Record<string, string> = {
  name: 'Nom',
  reference: 'Référence',
  serial_number: 'N° série',
  model: 'Modèle',
  manufacturer: 'Fabricant',
  address: 'Adresse',
  latitude: 'Latitude',
  longitude: 'Longitude',
  firmware_version: 'Version firmware',
  ocpp_version: 'Version OCPP',
  ocpp_identifier: 'Identifiant OCPP',
  declared_connector_count: 'Nombre de connecteurs',
  power_kw: 'Puissance (kW)',
  operational_status: 'État opérationnel',
  administrative_status: 'Statut administratif',
  site_id: 'Site',
  connector_number: 'Numéro de connecteur',
  standard: 'Standard',
  current_type: 'Type de courant',
  max_power_kw: 'Puissance max (kW)',
};

// Administrative status translations (charging stations — 4 values)
export const ADMIN_STATUS_LABELS: Record<string, string> = {
  commissioning: 'En mise en service',
  active: 'Actif',
  disabled: 'Désactivé',
  decommissioned: 'Décommissionné',
};

// Operational status translations (shared between stations and connectors)
export const OP_STATUS_LABELS: Record<string, string> = {
  available: 'Disponible',
  occupied: 'Occupée',
  out_of_service: 'Hors service',
  maintenance: 'Maintenance',
  disconnected: 'Déconnectée',
  fault: 'Défaut',
};

// History event type translations
export const EVENT_TYPE_LABELS: Record<string, string> = {
  created: 'Création',
  updated: 'Modification',
  state_changed: 'Changement d\'état',
  assigned: 'Affectation',
  disabled: 'Désactivation',
  reactivated: 'Réactivation',
  decommissioned: 'Décommissionnement',
};

// Connector standard translations
export const STANDARD_LABELS: Record<string, string> = {
  ccs: 'CCS',
  type2: 'Type 2',
  chademo: 'CHAdeMO',
};

// Connector current type translations
export const CURRENT_TYPE_LABELS: Record<string, string> = {
  ac: 'AC',
  dc: 'DC',
};

// Connector administrative status translations (connectors — 2 values, distinct from station's 4-value set)
export const CONNECTOR_ADMIN_STATUS_LABELS: Record<string, string> = {
  enabled: 'Activé',
  disabled: 'Désactivé',
};

// Format a single value for display, given its field name.
// - status enums get their French label
// - site_id gets resolved to the site name via a lookup
// - everything else displays as-is (or "—" for null)
export function formatValue(
  field: string,
  value: unknown,
  siteLookup?: Record<number, string>,
): string {
  if (value === null || value === undefined) return '—';

  if (field === 'administrative_status') {
    return ADMIN_STATUS_LABELS[String(value)] ?? String(value);
  }
  if (field === 'operational_status') {
    return OP_STATUS_LABELS[String(value)] ?? String(value);
  }
  if (field === 'standard') {
    return STANDARD_LABELS[String(value)] ?? String(value);
  }
  if (field === 'current_type') {
    return CURRENT_TYPE_LABELS[String(value)] ?? String(value);
  }
  if (field === 'site_id') {
    const id = Number(value);
    return siteLookup?.[id] ?? `Site #${id}`;
  }

  return String(value);
}

// Format an entire {field: value} object into "Label: value" lines.
export function formatChangeSet(
  values: Record<string, unknown> | null,
  siteLookup?: Record<number, string>,
): string[] {
  if (!values) return [];
  return Object.entries(values).map(([field, value]) => {
    const label = FIELD_LABELS[field] ?? field;
    return `${label} : ${formatValue(field, value, siteLookup)}`;
  });
}
