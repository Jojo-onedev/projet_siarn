import { useState } from 'react';
import { Navigate } from 'react-router-dom';
import { AuthLayout } from '../layout/AuthLayout';
import { Bouton } from '../components/ui/Bouton';
import { Alerte } from '../components/ui/Alerte';
import { useAuth } from '../auth/AuthContext';
import { ErreurApi } from '../api/client';

export default function MfaVerificationPage() {
  const { mfaEnAttente, validerMfa } = useAuth();
  const [code, setCode] = useState('');
  const [erreur, setErreur] = useState(null);
  const [enCours, setEnCours] = useState(false);

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
      </form>
    </AuthLayout>
  );
}
