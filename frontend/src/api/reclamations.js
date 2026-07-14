import { client } from './client';

export function listerReclamations(statut) {
  return client.get(`/reclamations${statut ? `?statut=${statut}` : ''}`);
}
export const creerReclamation = (donnees) => client.post('/reclamations', donnees);
export const repondreReclamation = (id, donnees) => client.post(`/reclamations/${id}/repondre`, donnees);
