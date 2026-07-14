import { useEffect, useState } from 'react';
import { listerReclamations, repondreReclamation } from '../../api/reclamations';
import { Tableau } from '../../components/ui/Tableau';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { Modale } from '../../components/ui/Modale';
import { ErreurApi } from '../../api/client';
import { libelleStatutReclamation, teinteStatutReclamation } from '../portail/statutsReclamations';
import '../referentiels/referentiels.css';

export default function ReclamationsPage() {
  const [reclamations, setReclamations] = useState([]);
  const [statut, setStatut] = useState('');
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [enTraitement, setEnTraitement] = useState(null);

  useEffect(() => { charger(); }, [statut]);

  function charger() {
    setChargement(true);
    listerReclamations(statut).then(setReclamations).catch(() => setErreur('Impossible de charger les réclamations.')).finally(() => setChargement(false));
  }

  const colonnes = [
    { cle: 'etudiant', entete: 'Étudiant', rendu: (r) => r.etudiant ? `${r.etudiant.matricule} — ${r.etudiant.nom} ${r.etudiant.prenom}` : '—' },
    { cle: 'motif', entete: 'Motif' },
    { cle: 'statut', entete: 'Statut', rendu: (r) => <Badge teinte={teinteStatutReclamation(r.statut)}>{libelleStatutReclamation(r.statut)}</Badge> },
    { cle: 'date_creation', entete: 'Déposée le', rendu: (r) => new Date(r.date_creation).toLocaleDateString('fr-FR') },
  ];

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Réclamations</p>
        <h1>Traitement des réclamations étudiantes</h1>
      </div>

      <div className="section-entete">
        <div className="filtres">
          <Select label="Statut" value={statut} onChange={(e) => setStatut(e.target.value)}>
            <option value="">Toutes</option>
            <option value="ouverte">Ouverte</option>
            <option value="en_traitement">En traitement</option>
            <option value="resolue">Résolue</option>
            <option value="rejetee">Rejetée</option>
          </Select>
        </div>
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <Tableau
          colonnes={colonnes}
          lignes={reclamations}
          cleLigne="id"
          surLigneClic={(r) => setEnTraitement(r)}
          vide="Aucune réclamation ne correspond a ces criteres."
        />
      )}

      {enTraitement ? (
        <FormulaireReponse
          reclamation={enTraitement}
          onFermer={() => setEnTraitement(null)}
          onTermine={() => { setEnTraitement(null); charger(); }}
        />
      ) : null}
    </div>
  );
}

function FormulaireReponse({ reclamation, onFermer, onTermine }) {
  const [statut, setStatut] = useState(reclamation.statut === 'ouverte' ? 'en_traitement' : reclamation.statut);
  const [reponse, setReponse] = useState(reclamation.reponse ?? '');
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function gererEnvoi(e) {
    e.preventDefault();
    setErreur(null);
    setEnCours(true);
    try {
      await repondreReclamation(reclamation.id, { statut, reponse });
      onTermine();
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Envoi impossible.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <Modale titre="Répondre à la réclamation" onFermer={onFermer}>
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
        <Alerte type="info" titre={reclamation.etudiant ? `${reclamation.etudiant.prenom} ${reclamation.etudiant.nom}` : 'Étudiant'}>
          {reclamation.motif}
        </Alerte>
        <Select label="Statut" value={statut} onChange={(e) => setStatut(e.target.value)}>
          <option value="en_traitement">En traitement</option>
          <option value="resolue">Résolue</option>
          <option value="rejetee">Rejetée</option>
        </Select>
        <div className="champ">
          <label className="champ__label" htmlFor="reponse-reclamation">Réponse</label>
          <textarea
            id="reponse-reclamation"
            className="champ__input"
            rows={4}
            required
            maxLength={2000}
            value={reponse}
            onChange={(e) => setReponse(e.target.value)}
          />
        </div>
        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onFermer}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours} disabled={!reponse.trim()}>Envoyer la réponse</Bouton>
        </div>
      </form>
    </Modale>
  );
}
