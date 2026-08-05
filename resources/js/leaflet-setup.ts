// resources/js/leaflet-setup.ts
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet's default marker icon paths assume a traditional non-bundled
// setup and break silently under Vite. We don't use the default marker
// anywhere (stations render as custom colored circle markers via
// stationMarkerState.ts), but this fixes the fallback defensively so any
// future default L.Marker usage doesn't render a broken image icon.
delete (L.Icon.Default.prototype as unknown as { _getIconUrl?: unknown })._getIconUrl;

L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});
