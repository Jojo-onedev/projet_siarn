import { useState } from 'react';
import { Modale } from '../components/ui/Modale';
import { Champ } from '../components/ui/Champ';
import { Bouton } from '../components/ui/Bouton';
import { Alerte } from '../components/ui/Alerte';
import { changerMotDePasse } from '../api/auth';
import { ErreurApi } from '../api/client';

// Jusqu'ici aucun ecran ne permettait a un utilisateur de changer son propre
// mot de passe (trouve en revue manuelle) - une fois defini a la creation du
// compte, il etait fige pour toujours.
export function ChangerMotDePasseModale({ onFermer }) {
  const [motDePasseActuel, setMotDePasseActuel] = useState('');
  const [nouveauMotDePasse, setNouveauMotDePasse] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [erreur, setErreur] = useState(null);
  const [succes, setSucces] = useState(false);
  const [enCours, setEnCours] = useState(false);

  async function gererEnvoi(e) {
    e.preventDefault();
    setErreur(null);
    if (nouveauMotDePasse !== confirmation) {
      setErreur('La confirmation ne correspond pas au nouveau mot de passe.');
      return;
    }
    setEnCours(true);
    try {
      await changerMotDePasse(motDePasseActuel, nouveauMotDePasse);
      setSucces(true);
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Une erreur inattendue est survenue.');
    } finally {
      setEnCours(false);
    }
  }

  if (succes) {
    return (
      <Modale titre="Mot de passe modifié" onFermer={onFermer}>
        <div className="formulaire">
          <Alerte type="succes">Votre mot de passe a été modifié avec succès.</Alerte>
          <div className="formulaire__actions">
            <Bouton onClick={onFermer}>Fermer</Bouton>
          </div>
        </div>
      </Modale>
    );
  }

  return (
    <Modale titre="Changer mon mot de passe" onFermer={onFermer}>
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
        <Champ
          label="Mot de passe actuel"
          type="password"
          autoComplete="current-password"
          required
          value={motDePasseActuel}
          onChange={(e) => setMotDePasseActuel(e.target.value)}
        />
        <Champ
          label="Nouveau mot de passe"
          type="password"
          autoComplete="new-password"
          required
          minLength={12}
          value={nouveauMotDePasse}
          onChange={(e) => setNouveauMotDePasse(e.target.value)}
          aide="12 caractères minimum."
        />
        <Champ
          label="Confirmer le nouveau mot de passe"
          type="password"
          autoComplete="new-password"
          required
          minLength={12}
          value={confirmation}
          onChange={(e) => setConfirmation(e.target.value)}
        />
        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onFermer}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours}>Enregistrer</Bouton>
        </div>
      </form>
    </Modale>
  );
}
