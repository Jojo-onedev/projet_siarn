import { client } from './client';

export function listerDocumentsCorpus(params = {}) {
  const query = new URLSearchParams(Object.fromEntries(Object.entries(params).filter(([, v]) => v))).toString();
  return client.get(`/corpus/documents${query ? `?${query}` : ''}`);
}
export const obtenirDocumentCorpus = (id) => client.get(`/corpus/documents/${id}`);

export function importerDocumentCorpus({ fichier, type_gabarit, jeu, anonymise }) {
  const formData = new FormData();
  formData.append('fichier', fichier);
  if (type_gabarit) formData.append('type_gabarit', type_gabarit);
  if (jeu) formData.append('jeu', jeu);
  formData.append('anonymise', anonymise ? '1' : '0');
  return client.postMultipart('/corpus/documents', formData);
}

export const annoterDocumentCorpus = (id, donnees) => client.post(`/corpus/documents/${id}/annotations`, donnees);
export const repartirCorpus = () => client.post('/corpus/repartir', {});
