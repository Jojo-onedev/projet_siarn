import { useState } from 'react';
import { useLocation } from 'react-router-dom';
import { AuthLayout } from '../layout/AuthLayout';
import { Champ } from '../components/ui/Champ';
import { Bouton } from '../components/ui/Bouton';
import { Alerte } from '../components/ui/Alerte';
import { useAuth } from '../auth/AuthContext';
import { ErreurApi } from '../api/client';

export default function LoginPage() {
  const { seConnecter } = useAuth();
  const location = useLocation();
  const [email, setEmail] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [erreur, setErreur] = useState(null);
  const [enCours, setEnCours] = useState(false);

  const sessionExpiree = location.state?.motif === 'session_expiree';

  async function gererEnvoi(evenement) {
    evenement.preventDefault();
    setErreur(null);
    setEnCours(true);
    try {
      await seConnecter(email, motDePasse);
    } catch (e) {
      if (e instanceof ErreurApi && e.statut === 423) {
        setErreur('Compte verrouille temporairement suite a plusieurs echecs. Reessayez plus tard.');
      } else if (e instanceof ErreurApi && e.statut === 429) {
        setErreur('Trop de tentatives de connexion. Reessayez dans quelques instants.');
      } else if (e instanceof ErreurApi) {
        setErreur(e.message);
      } else {
        setErreur('Une erreur inattendue est survenue.');
      }
    } finally {
      setEnCours(false);
    }
  }

  return (
    <AuthLayout
      eyebrow="Espace personnel"
      titre="Connexion"
      sousTitre="Accedez a votre espace SIARN avec vos identifiants d'etablissement."
    >
      {sessionExpiree ? (
        <Alerte type="avertissement" titre="Session expiree">
          Merci de vous reconnecter.
        </Alerte>
      ) : null}

      <form className="auth-formulaire" onSubmit={gererEnvoi} noValidate>
        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}

        <Champ
          label="Adresse e-mail"
          type="email"
          name="email"
          autoComplete="username"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
        />
        <Champ
          label="Mot de passe"
          type="password"
          name="mot_de_passe"
          autoComplete="current-password"
          required
          value={motDePasse}
          onChange={(e) => setMotDePasse(e.target.value)}
        />

        <Bouton type="submit" pleineLargeur chargement={enCours}>
          Se connecter
        </Bouton>
      </form>

      <p className="auth-formulaire__pied">
        Un probleme d'acces ? Contactez l'agent de scolarite de votre etablissement.
      </p>
    </AuthLayout>
  );
}
