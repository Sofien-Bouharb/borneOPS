import type { AxiosError } from 'axios';
import { ADMIN_STATUS_LABELS, FIELD_LABELS } from './stationLabels';

// Legacy English → French translation maps.
// Kept empty because backend exception messages are now French at source
// (as of the July 29 hardening pass). This scaffolding remains in place
// so future non-French messages can be translated here without new
// architecture — add entries below if any English message is ever
// reintroduced on the backend.
const STATIC_MESSAGES: Record<string, string> = {};
const DYNAMIC_PATTERNS: Array<{ regex: RegExp; format: (match: RegExpMatchArray) => string }> = [];

export function translateBackendMessage(message: string): string {
  if (STATIC_MESSAGES[message]) return STATIC_MESSAGES[message];
  for (const { regex, format } of DYNAMIC_PATTERNS) {
    const match = message.match(regex);
    if (match) return format(match);
  }
  return message; // fallback to raw English
}

// Extract a display-friendly French message from an Axios error.
// Handles: network errors, 409 (business rules), 422 (validation), and generic fallbacks.
export function extractErrorMessage(err: unknown): string {
  const error = err as AxiosError<{ message?: string; errors?: Record<string, string[]> }>;

  // No response at all — network error, server down, CORS, etc.
  if (!error.response) {
    return 'Erreur de connexion au serveur.';
  }

  const status = error.response.status;
  const data = error.response.data;

  // 422 validation error — flatten field errors into one readable line
  if (status === 422 && data?.errors) {
    const firstField = Object.keys(data.errors)[0];
    if (firstField) {
      const firstMessage = data.errors[firstField][0];
      // If it's one of our known service messages routed through validation, translate it
      return translateBackendMessage(firstMessage);
    }
  }

  // 409 or other with a `message` field — translate if we can
  if (data?.message) {
    return translateBackendMessage(data.message);
  }

  // Generic fallbacks by status code
  if (status === 401) return 'Session expirée. Veuillez vous reconnecter.';
  if (status === 403) return 'Vous n\'avez pas les droits nécessaires pour cette action.';
  if (status === 404) return 'Ressource introuvable.';
  if (status >= 500) return 'Erreur serveur. Veuillez réessayer plus tard.';

  return 'Une erreur inattendue est survenue.';
}
