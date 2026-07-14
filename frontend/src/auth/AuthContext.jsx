import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { configurerClientApi } from '../api/client';
import * as authApi from '../api/auth';

const AuthContext = createContext(null);

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
    navigate('/', { replace: true });
    return resultat;
  }, [navigate]);

  const validerMfa = useCallback(async (code) => {
    const resultat = await authApi.verifierMfa(mfaToken, code);
    setToken(resultat.token);
    setUtilisateur(resultat.utilisateur);
    setMfaToken(null);
    navigate('/', { replace: true });
    return resultat;
  }, [mfaToken, navigate]);

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
