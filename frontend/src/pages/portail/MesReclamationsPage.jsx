import { useEffect, useState } from 'react';
import { listerMesReclamations, creerReclamation } from '../../api/reclamations';
import { Tableau } from '../../components/ui/Tableau';
import { Badge } from '../../components/ui/Badge';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Modale } from '../../components/ui/Modale';
import { ErreurApi } from '../../api/client';
import { libelleStatutReclamation, teinteStatutReclamation } from './statutsReclamations';
import '../pages.css';

export default function MesReclamationsPage() {
  const [reclamations, setReclamations] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [modaleOuverte, setModaleOuverte] = useState(false);

  useEffect(() => { charger(); }, []);

  function charger() {
    setChargement(true);
    listerMesReclamations().then(setReclamations).catch(() => setErreur('Impossible de charger vos réclamations.')).finally(() => setChargement(false));
  }

  const colonnes = [
    { cle: 'motif', entete: 'Motif' },
    { cle: 'statut', entete: 'Statut', rendu: (r) => <Badge teinte={teinteStatutReclamation(r.statut)}>{libelleStatutReclamation(r.statut)}</Badge> },
    { cle: 'reponse', entete: 'Réponse', rendu: (r) => r.reponse ?? '—' },
    { cle: 'date_creation', entete: 'Déposée le', rendu: (r) => new Date(r.date_creation).toLocaleDateString('fr-FR') },
  ];

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Mon espace</p>
        <h1>Mes réclamations</h1>
        <p>Signalez une erreur sur une note publiée ou une question de scolarité.</p>
      </div>

      <div className="section-entete">
        <span />
        <Bouton onClick={() => setModaleOuverte(true)}>Nouvelle réclamation</Bouton>
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <Tableau colonnes={colonnes} lignes={reclamations} cleLigne="id" vide="Aucune réclamation pour l'instant." />
      )}

      {modaleOuverte ? (
        <FormulaireReclamation
          onFermer={() => setModaleOuverte(false)}
          onTermine={() => { setModaleOuverte(false); charger(); }}
        />
      ) : null}
    </div>
  );
}

function FormulaireReclamation({ onFermer, onTermine }) {
  const [motif, setMotif] = useState('');
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function gererEnvoi(e) {
    e.preventDefault();
    setErreur(null);
    setEnCours(true);
    try {
      await creerReclamation({ motif });
      onTermine();
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Envoi impossible.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <Modale titre="Nouvelle réclamation" onFermer={onFermer}>
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
        <div className="champ">
          <label className="champ__label" htmlFor="motif-reclamation">Décrivez votre demande</label>
          <textarea
            id="motif-reclamation"
            className="champ__input"
            rows={5}
            required
            maxLength={2000}
            value={motif}
            onChange={(e) => setMotif(e.target.value)}
          />
        </div>
        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onFermer}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours} disabled={!motif.trim()}>Envoyer</Bouton>
        </div>
      </form>
    </Modale>
  );
}
