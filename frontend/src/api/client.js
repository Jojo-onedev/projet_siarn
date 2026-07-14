// Client HTTP fin vers backend-api — pas de lib de data-fetching (volume
// d'écrans encore modeste, cf. PRD_FRONTEND.md §8). Le token JWT est
// injecté par un getter (jamais lu depuis localStorage ici : stocké en
// mémoire par AuthContext, cf. .agents/frontend-security-coder).

const BASE_URL = `${import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000'}/api`;

let tokenGetter = () => null;
let onUnauthorized = () => {};
let onMfaRequise = () => {};

export function configurerClientApi({ getToken, onSessionExpiree, onMfaObligatoire }) {
  if (getToken) tokenGetter = getToken;
  if (onSessionExpiree) onUnauthorized = onSessionExpiree;
  if (onMfaObligatoire) onMfaRequise = onMfaObligatoire;
}

export class ErreurApi extends Error {
  constructor(message, { statut, code, erreurs } = {}) {
    super(message);
    this.name = 'ErreurApi';
    this.statut = statut;
    this.code = code;
    this.erreurs = erreurs ?? null;
  }
}

async function requete(chemin, { method = 'GET', corps, entetes = {}, estMultipart = false } = {}) {
  const token = tokenGetter();
  const enTetes = { Accept: 'application/json', ...entetes };
  if (!estMultipart) {
    enTetes['Content-Type'] = 'application/json';
  }
  if (token) {
    enTetes.Authorization = `Bearer ${token}`;
  }

  let reponse;
  try {
    reponse = await fetch(`${BASE_URL}${chemin}`, {
      method,
      headers: enTetes,
      body: corps === undefined ? undefined : estMultipart ? corps : JSON.stringify(corps),
    });
  } catch {
    throw new ErreurApi('Impossible de joindre le serveur. Vérifiez votre connexion.', { statut: 0 });
  }

  const texte = await reponse.text();
  const donnees = texte ? JSON.parse(texte) : null;

  if (!reponse.ok) {
    // Une session authentifiée qui expire déclenche la déconnexion globale ;
    // un 401 sur /auth/connexion (mauvais mot de passe) reste local à l'écran
    // de connexion, on ne le confond jamais avec une session expirée.
    if (reponse.status === 401 && token) {
      onUnauthorized();
    }
    if (reponse.status === 403 && donnees?.code === 'MFA_REQUIS') {
      onMfaRequise();
    }
    throw new ErreurApi(donnees?.message ?? 'Une erreur est survenue.', {
      statut: reponse.status,
      code: donnees?.code,
      erreurs: donnees?.errors,
    });
  }

  return donnees;
}

// Reponse binaire (image) - pas de parsing JSON, cf. PvController::image.
async function requeteBlob(chemin) {
  const token = tokenGetter();
  const reponse = await fetch(`${BASE_URL}${chemin}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });
  if (!reponse.ok) {
    if (reponse.status === 401 && token) onUnauthorized();
    throw new ErreurApi('Image indisponible.', { statut: reponse.status });
  }
  return reponse.blob();
}

export const client = {
  get: (chemin) => requete(chemin),
  post: (chemin, corps) => requete(chemin, { method: 'POST', corps }),
  put: (chemin, corps) => requete(chemin, { method: 'PUT', corps }),
  postMultipart: (chemin, formData) => requete(chemin, { method: 'POST', corps: formData, estMultipart: true }),
  getBlob: (chemin) => requeteBlob(chemin),
};
