import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { configurerClientApi } from '../api/client';
import * as authApi from '../api/auth';

const AuthContext = createContext(null);

// Miroir de App\Enums\RoleUtilisateur::mfaObligatoire() (backend-api) - sert
// uniquement a rediriger PROACTIVEMENT vers l'enrolement juste apres la
// connexion, plutot que d'attendre un premier appel metier qui echoue en
// 403 MFA_REQUIS (source de confusion reelle : le tableau de bord semblait
// accessible avant qu'un clic sur un ecran metier ne renvoie a l'enrolement).
// Le 403 MFA_REQUIS intercepte globalement (cf. api/client.js) reste le
// filet de securite reel si ce miroir se desynchronise du backend.
const ROLES_MFA_OBLIGATOIRE = ['agent_scolarite', 'chef_departement', 'responsable_academique', 'admin', 'directeur'];

// Le JWT vit uniquement en mémoire (jamais localStorage/sessionStorage) :
// une session survit à une navigation interne mais pas à un rechargement de
// page, compromis assumé tant qu'aucun refresh token n'existe côté backend
// (cf. PRD_FRONTEND.md §3, .agents/frontend-security-coder sur le stockage
// des tokens).
export function AuthProvider({ children }) {
  const [token, setToken] = useState(null);
  const [utilisateur, setUtilisateur] = useState(null);
  const [mfaToken, setMfaToken] = useState(null);
  const [pret, setPret] = useState(false);
  const navigate = useNavigate();
  const tokenRef = useRef(null);
  tokenRef.current = token;

  useEffect(() => {
    configurerClientApi({
      getToken: () => tokenRef.current,
      onSessionExpiree: () => {
        setToken(null);
        setUtilisateur(null);
        navigate('/connexion', { state: { motif: 'session_expiree' }, replace: true });
      },
      onMfaObligatoire: () => {
        navigate('/mfa/activation', { replace: true });
      },
    });
    setPret(true);
  }, [navigate]);

  const seConnecter = useCallback(async (email, motDePasse) => {
    const resultat = await authApi.connexion(email, motDePasse);
    if (resultat.statut === 'mfa_requis') {
      setMfaToken(resultat.mfa_token);
      navigate('/mfa/verification');
      return resultat;
    }
    setToken(resultat.token);
    setUtilisateur(resultat.utilisateur);
    naviguerApresConnexion(resultat.utilisateur);
    return resultat;
  }, [navigate]);

  const validerMfa = useCallback(async (code) => {
    const resultat = await authApi.verifierMfa(mfaToken, code);
    setToken(resultat.token);
    setUtilisateur(resultat.utilisateur);
    setMfaToken(null);
    naviguerApresConnexion(resultat.utilisateur);
    return resultat;
  }, [mfaToken, navigate]);

  function naviguerApresConnexion(utilisateurConnecte) {
    const doitEnroler = ROLES_MFA_OBLIGATOIRE.includes(utilisateurConnecte.role) && !utilisateurConnecte.statut_mfa;
    navigate(doitEnroler ? '/mfa/activation' : '/', { replace: true });
  }

  const demarrerEnrolementMfa = useCallback(() => authApi.activerMfa(), []);

  const confirmerEnrolementMfa = useCallback(async (code) => {
    await authApi.confirmerMfa(code);
    setUtilisateur((u) => (u ? { ...u, statut_mfa: true } : u));
    navigate('/', { replace: true });
  }, [navigate]);

  const seDeconnecter = useCallback(async () => {
    try {
      await authApi.deconnexion();
    } catch {
      // Deconnexion cote serveur en best-effort : on nettoie l'etat local
      // dans tous les cas (jeton potentiellement deja expire).
    }
    setToken(null);
    setUtilisateur(null);
    navigate('/connexion', { replace: true });
  }, [navigate]);

  const valeur = useMemo(() => ({
    token,
    utilisateur,
    estConnecte: Boolean(token && utilisateur),
    mfaEnAttente: Boolean(mfaToken),
    pret,
    seConnecter,
    validerMfa,
    demarrerEnrolementMfa,
    confirmerEnrolementMfa,
    seDeconnecter,
  }), [token, utilisateur, mfaToken, pret, seConnecter, validerMfa, demarrerEnrolementMfa, confirmerEnrolementMfa, seDeconnecter]);

  return <AuthContext.Provider value={valeur}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const contexte = useContext(AuthContext);
  if (!contexte) {
    throw new Error('useAuth doit etre utilise a l\'interieur de AuthProvider.');
  }
  return contexte;
}
