import { client } from './client';

export const listerFilieres = () => client.get('/filieres');
export const creerFiliere = (donnees) => client.post('/filieres', donnees);
export const modifierFiliere = (id, donnees) => client.put(`/filieres/${id}`, donnees);

export const listerModules = (filiereId) =>
  client.get(`/modules${filiereId ? `?filiere_id=${filiereId}` : ''}`);
export const creerModule = (donnees) => client.post('/modules', donnees);
export const modifierModule = (id, donnees) => client.put(`/modules/${id}`, donnees);

export function listerEtudiants(params = {}) {
  const query = new URLSearchParams(
    Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null))
  ).toString();
  return client.get(`/etudiants${query ? `?${query}` : ''}`);
}
export const creerEtudiant = (donnees) => client.post('/etudiants', donnees);
export const modifierEtudiant = (id, donnees) => client.put(`/etudiants/${id}`, donnees);
export const calculerMoyenne = (id, semestre, anneeAcademique) =>
  client.get(`/etudiants/${id}/moyenne?semestre=${encodeURIComponent(semestre)}&annee_academique=${encodeURIComponent(anneeAcademique)}`);

export function importerEtudiants(fichier) {
  const formData = new FormData();
  formData.append('fichier', fichier);
  return client.postMultipart('/etudiants/import', formData);
}
