import { client } from './client';

export function listerAbsences(params = {}) {
  const query = new URLSearchParams(Object.fromEntries(Object.entries(params).filter(([, v]) => v))).toString();
  return client.get(`/absences${query ? `?${query}` : ''}`);
}

export const creerAbsence = (donnees) => client.post('/absences', donnees);
