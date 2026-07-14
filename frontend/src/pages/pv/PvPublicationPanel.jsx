import { useState } from 'react';
import { publierPv } from '../../api/pv';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';

// §7.7/§9.1 : action deliberee, jamais en cascade automatique - rend les
// notes visibles a l'etudiant et declenche la notification de publication.
export default function PvPublicationPanel({ pv, onMisAJour }) {
  const [confirmation, setConfirmation] = useState(false);
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function publier() {
    setErreur(null);
    setEnCours(true);
    try {
      const pvMisAJour = await publierPv(pv.id);
      onMisAJour(pvMisAJour);
    } catch (err) {
      setErreur(err?.message ?? 'Publication impossible.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <div className="formulaire">
      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      <p>Le dossier est intégré. La publication rend les notes visibles aux étudiants et envoie une notification.</p>
      {confirmation ? (
        <div className="formulaire__actions">
          <Bouton variante="secondaire" onClick={() => setConfirmation(false)}>Annuler</Bouton>
          <Bouton onClick={publier} chargement={enCours}>Confirmer la publication</Bouton>
        </div>
      ) : (
        <div className="formulaire__actions">
          <Bouton onClick={() => setConfirmation(true)}>Publier les résultats</Bouton>
        </div>
      )}
    </div>
  );
}
