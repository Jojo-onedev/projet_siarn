import { client } from './client';

// Reserve a l'admin cote backend (§5 RBAC) - n'appeler ces fonctions que
// lorsque utilisateur.role === 'admin'.
export const listerUtilisateurs = () => client.get('/utilisateurs');
export const creerUtilisateur = (donnees) => client.post('/utilisateurs', donnees);
export const modifierUtilisateur = (id, donnees) => client.put(`/utilisateurs/${id}`, donnees);
