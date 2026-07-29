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
};

// Administrative status translations
export const ADMIN_STATUS_LABELS: Record<string, string> = {
  commissioning: 'En mise en service',
  active: 'Actif',
  disabled: 'Désactivé',
  decommissioned: 'Décommissionné',
};

// Operational status translations
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
