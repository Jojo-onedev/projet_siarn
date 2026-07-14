import { client } from './client';

export function listerPv(params = {}) {
  const query = new URLSearchParams(
    Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null))
  ).toString();
  return client.get(`/pv${query ? `?${query}` : ''}`);
}

export const obtenirPv = (id) => client.get(`/pv/${id}`);

export const obtenirImagePv = (id, type = 'original') => client.getBlob(`/pv/${id}/image?type=${type}`);

export const listerNotesPv = (id) => client.get(`/pv/${id}/notes`);

export function importerPv(fichiers, champs) {
  const formData = new FormData();
  for (const f of fichiers) formData.append('fichiers[]', f);
  for (const [cle, valeur] of Object.entries(champs)) {
    if (valeur !== '' && valeur != null) formData.append(cle, valeur);
  }
  return client.postMultipart('/pv/import', formData);
}

export const verifierPv = (id, corrections) => client.post(`/pv/${id}/verifier`, { corrections });
export const saisirNotePv = (id, donnees) => client.post(`/pv/${id}/notes`, donnees);
export const validerPv = (id, decision, motif) => client.post(`/pv/${id}/valider`, { decision, motif });
export const publierPv = (id) => client.post(`/pv/${id}/publier`, {});
