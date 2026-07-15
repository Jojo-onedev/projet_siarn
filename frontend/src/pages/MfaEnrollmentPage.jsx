import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { AuthLayout } from '../layout/AuthLayout';
import { Bouton } from '../components/ui/Bouton';
import { Alerte } from '../components/ui/Alerte';
import { useAuth } from '../auth/AuthContext';
import { ErreurApi } from '../api/client';

export default function MfaEnrollmentPage() {
  const { demarrerEnrolementMfa, confirmerEnrolementMfa, seDeconnecter } = useAuth();
  const [enrolement, setEnrolement] = useState(null);
  const [qrDataUrl, setQrDataUrl] = useState(null);
  const [afficherCleManuelle, setAfficherCleManuelle] = useState(false);
  const [code, setCode] = useState('');
  const [erreur, setErreur] = useState(null);
  const [enCours, setEnCours] = useState(false);
  const [chargementInitial, setChargementInitial] = useState(true);
  const [copie, setCopie] = useState(false);

  useEffect(() => {
    let annule = false;
    demarrerEnrolementMfa()
      .then(async (resultat) => {
        if (annule) return;
        setEnrolement(resultat);
        try {
          // Genere le QR entierement dans le navigateur, a partir de l'URI
          // otpauth deja renvoyee par le backend - le secret ne transite
          // jamais vers un service tiers de generation d'image QR (ce
          // serait une fuite du secret MFA en clair sur le reseau).
          const dataUrl = await QRCode.toDataURL(resultat.uri_provisionnement, { width: 220, margin: 1 });
          if (!annule) setQrDataUrl(dataUrl);
        } catch {
          // Generation QR indisponible : la cle manuelle reste utilisable, pas d'erreur bloquante.
          if (!annule) setAfficherCleManuelle(true);
        }
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
              <p>1. Ouvrez une application d'authentification (Google Authenticator, Authy, etc.) sur votre telephone.</p>
            </li>
            <li>
              {qrDataUrl ? (
                <div className="auth-qr">
                  <img src={qrDataUrl} alt="QR code a scanner avec votre application d'authentification" width={220} height={220} />
                  <p className="auth-formulaire__pied" style={{ marginTop: 'var(--space-2)' }}>Scannez ce code avec l'appareil photo de l'application.</p>
                </div>
              ) : (
                <p>Generation du QR code…</p>
              )}
            </li>
            <li>
              {!afficherCleManuelle ? (
                <Bouton type="button" variante="fantome" onClick={() => setAfficherCleManuelle(true)}>
                  Impossible de scanner ? Saisir la cle manuellement
                </Bouton>
              ) : (
                <div className="auth-secret">
                  <span className="champ__label">Cle secrete a saisir manuellement</span>
                  <code className="auth-secret__valeur">{enrolement.secret}</code>
                  <Bouton type="button" variante="secondaire" onClick={copierSecret}>
                    {copie ? 'Copie !' : 'Copier la cle'}
                  </Bouton>
                </div>
              )}
            </li>
            <li>
              <p>2. Entrez ensuite le code a 6 chiffres genere par l'application.</p>
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
            <Bouton type="button" variante="fantome" pleineLargeur onClick={seDeconnecter}>
              Annuler et se deconnecter
            </Bouton>
          </form>
        </>
      )}
    </AuthLayout>
  );
}
