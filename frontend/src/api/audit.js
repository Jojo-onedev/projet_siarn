import { client } from './client';

export function listerAudit(params = {}) {
  const query = new URLSearchParams(Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null))).toString();
  return client.get(`/audit${query ? `?${query}` : ''}`);
}
