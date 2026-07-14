import { useEffect, useState } from 'react';
import { AuthLayout } from '../layout/AuthLayout';
import { Bouton } from '../components/ui/Bouton';
import { Alerte } from '../components/ui/Alerte';
import { useAuth } from '../auth/AuthContext';
import { ErreurApi } from '../api/client';

export default function MfaEnrollmentPage() {
  const { demarrerEnrolementMfa, confirmerEnrolementMfa } = useAuth();
  const [enrolement, setEnrolement] = useState(null);
  const [code, setCode] = useState('');
  const [erreur, setErreur] = useState(null);
  const [enCours, setEnCours] = useState(false);
  const [chargementInitial, setChargementInitial] = useState(true);
  const [copie, setCopie] = useState(false);

  useEffect(() => {
    let annule = false;
    demarrerEnrolementMfa()
      .then((resultat) => {
        if (!annule) setEnrolement(resultat);
      })
      .catch(() => {
        if (!annule) setErreur('Impossible de generer le secret MFA. Reessayez.');
      })
      .finally(() => {
        if (!annule) setChargementInitial(false);
      });
    return () => { annule = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function copierSecret() {
    try {
      await navigator.clipboard.writeText(enrolement.secret);
      setCopie(true);
      setTimeout(() => setCopie(false), 2000);
    } catch {
      // Presse-papiers indisponible (contexte non securise) : le secret
      // reste selectionnable manuellement, pas d'erreur bloquante.
    }
  }

  async function gererEnvoi(evenement) {
    evenement.preventDefault();
    setErreur(null);
    setEnCours(true);
    try {
      await confirmerEnrolementMfa(code);
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : 'Une erreur inattendue est survenue.');
      setCode('');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <AuthLayout
      eyebrow="Etape obligatoire"
      titre="Activer la double authentification"
      sousTitre="Votre role exige un second facteur de securite avant tout acces aux fonctionnalites metier."
    >
      {chargementInitial ? (
        <p>Generation du secret en cours…</p>
      ) : erreur && !enrolement ? (
        <Alerte type="erreur">{erreur}</Alerte>
      ) : (
        <>
          <ol className="auth-formulaire" style={{ gap: 'var(--space-4)' }}>
            <li>
              <p>1. Ouvrez une application d'authentification (Google Authenticator, Authy, etc.) et ajoutez un compte manuellement.</p>
            </li>
            <li className="auth-secret">
              <span className="champ__label">Cle secrete a saisir</span>
              <code className="auth-secret__valeur">{enrolement.secret}</code>
              <Bouton type="button" variante="secondaire" onClick={copierSecret}>
                {copie ? 'Copie !' : 'Copier la cle'}
              </Bouton>
            </li>
            <li>
              <p>2. Renseignez « SIARN » comme emetteur si demande, puis entrez le code a 6 chiffres genere.</p>
            </li>
          </ol>

          <form className="auth-formulaire" onSubmit={gererEnvoi} noValidate>
            {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
            <input
              className="auth-formulaire__code champ__input"
              inputMode="numeric"
              pattern="[0-9]*"
              autoComplete="one-time-code"
              maxLength={6}
              aria-label="Code de confirmation a 6 chiffres"
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
              required
            />
            <Bouton type="submit" pleineLargeur chargement={enCours} disabled={code.length !== 6}>
              Activer le MFA
            </Bouton>
          </form>
        </>
      )}
    </AuthLayout>
  );
}
