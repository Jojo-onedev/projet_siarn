import { useState } from 'react';
import { Navigate } from 'react-router-dom';
import { AuthLayout } from '../layout/AuthLayout';
import { Bouton } from '../components/ui/Bouton';
import { Alerte } from '../components/ui/Alerte';
import { useAuth } from '../auth/AuthContext';
import { ErreurApi } from '../api/client';

export default function MfaVerificationPage() {
  const { mfaEnAttente, estConnecte, validerMfa, annulerMfa } = useAuth();
  const [code, setCode] = useState('');
  const [erreur, setErreur] = useState(null);
  const [enCours, setEnCours] = useState(false);

  // Bug reel corrige ici : validerMfa() reussi met a jour token/utilisateur
  // ET remet mfaToken a null (donc mfaEnAttente=false) dans le meme lot de
  // rendu que son propre navigate('/'). Si ce composant se re-rend avant que
  // le changement de route ne soit effectif, l'ancienne condition
  // "if (!mfaEnAttente) -> /connexion" gagnait la course et renvoyait
  // l'utilisateur a la page de connexion malgre un code MFA valide. Verifier
  // estConnecte D'ABORD supprime cette course : une fois authentifie, on ne
  // revient plus jamais vers /connexion depuis cet ecran.
  if (estConnecte) {
    return <Navigate to="/" replace />;
  }
  if (!mfaEnAttente) {
    return <Navigate to="/connexion" replace />;
  }

  async function gererEnvoi(evenement) {
    evenement.preventDefault();
    setErreur(null);
    setEnCours(true);
    try {
      await validerMfa(code);
    } catch (e) {
      if (e instanceof ErreurApi && e.statut === 423) {
        setErreur('Compte verrouille temporairement suite a plusieurs echecs.');
      } else if (e instanceof ErreurApi && e.statut === 401) {
        // Jeton mfa_token expire (valide 5 minutes) ou invalide : aucun
        // nouveau code ne pourra jamais etre accepte sur cette page,
        // il faut repartir de la connexion pour obtenir un jeton frais.
        setErreur('Votre session de verification a expire (delai de 5 minutes depasse). Reconnectez-vous pour recevoir un nouveau code a saisir.');
      } else if (e instanceof ErreurApi) {
        setErreur(e.message);
      } else {
        setErreur('Une erreur inattendue est survenue.');
      }
      setCode('');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <AuthLayout
      eyebrow="Double authentification"
      titre="Code de verification"
      sousTitre="Saisissez le code a 6 chiffres genere par votre application d'authentification."
    >
      <form className="auth-formulaire" onSubmit={gererEnvoi} noValidate>
        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}

        <input
          className="auth-formulaire__code champ__input"
          inputMode="numeric"
          pattern="[0-9]*"
          autoComplete="one-time-code"
          maxLength={6}
          aria-label="Code de verification a 6 chiffres"
          value={code}
          onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
          autoFocus
          required
        />

        <Bouton type="submit" pleineLargeur chargement={enCours} disabled={code.length !== 6}>
          Valider
        </Bouton>
        <Bouton type="button" variante="fantome" pleineLargeur onClick={annulerMfa}>
          Retour a la connexion
        </Bouton>
      </form>
    </AuthLayout>
  );
}
