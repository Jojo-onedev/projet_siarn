import { client } from './client';

export const connexion = (email, motDePasse) =>
  client.post('/auth/connexion', { email, mot_de_passe: motDePasse });

export const verifierMfa = (mfaToken, code) =>
  client.post('/auth/mfa/verifier', { mfa_token: mfaToken, code });

export const activerMfa = () => client.post('/auth/mfa/activer', {});

export const confirmerMfa = (code) => client.post('/auth/mfa/confirmer', { code });

export const deconnexion = () => client.post('/auth/deconnexion', {});

export const moi = () => client.get('/auth/moi');

export const changerMotDePasse = (motDePasseActuel, nouveauMotDePasse) =>
  client.put('/auth/mot-de-passe', { mot_de_passe_actuel: motDePasseActuel, nouveau_mot_de_passe: nouveauMotDePasse });
