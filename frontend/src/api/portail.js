import { client } from './client';

export const obtenirMonProfil = () => client.get('/mon-profil');
export const listerMesNotes = (params = {}) => {
  const query = new URLSearchParams(Object.fromEntries(Object.entries(params).filter(([, v]) => v))).toString();
  return client.get(`/mes-notes${query ? `?${query}` : ''}`);
};
export const listerMesAlertes = () => client.get('/mes-alertes');
export const listerMesReclamations = () => client.get('/mes-reclamations');
