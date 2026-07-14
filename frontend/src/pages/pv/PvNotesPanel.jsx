import { useEffect, useState } from 'react';
import { useAuth } from '../../auth/AuthContext';
import { listerNotesPv, saisirNotePv } from '../../api/pv';
import { listerEtudiants } from '../../api/referentiels';
import { Select } from '../../components/ui/Select';
import { Champ } from '../../components/ui/Champ';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { ErreurApi } from '../../api/client';

export default function PvNotesPanel({ pv }) {
  const { utilisateur } = useAuth();
  const peutSaisir = utilisateur.role === 'agent_scolarite' && ['en_verification', 'en_validation'].includes(pv.statut);

  const [notes, setNotes] = useState([]);
  const [etudiants, setEtudiants] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [etudiantId, setEtudiantId] = useState('');
  const [valeur, setValeur] = useState('');
  const [erreur, setErreur] = useState(null);
  const [enCours, setEnCours] = useState(false);

  useEffect(() => {
    chargerNotes();
    if (pv.filiere?.id) {
      listerEtudiants({ filiere_id: pv.filiere.id, par_page: 500 }).then((r) => setEtudiants(r.donnees)).catch(() => {});
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pv.id]);

  function chargerNotes() {
    setChargement(true);
    listerNotesPv(pv.id).then(setNotes).finally(() => setChargement(false));
  }

  async function gererAjout(e) {
    e.preventDefault();
    setErreur(null);
    setEnCours(true);
    try {
      await saisirNotePv(pv.id, { etudiant_id: etudiantId, valeur: Number(valeur) });
      setEtudiantId('');
      setValeur('');
      chargerNotes();
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Enregistrement impossible.');
    } finally {
      setEnCours(false);
    }
  }

  const etudiantsRestants = etudiants.filter((e) => !notes.some((n) => n.etudiant?.id === e.id));

  return (
    <div>
      {peutSaisir ? (
        <form className="formulaire pv-notes__formulaire" onSubmit={gererAjout}>
          {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
          <div className="formulaire__grille">
            <Select label="Étudiant" required value={etudiantId} onChange={(e) => setEtudiantId(e.target.value)}>
              <option value="" disabled>Sélectionner…</option>
              {etudiantsRestants.map((e) => (
                <option key={e.id} value={e.id}>{e.matricule} — {e.nom} {e.prenom}</option>
              ))}
            </Select>
            <Champ label="Note (/20)" type="number" min="0" max="20" step="0.25" required value={valeur} onChange={(e) => setValeur(e.target.value)} />
          </div>
          <div className="formulaire__actions">
            <Bouton type="submit" chargement={enCours} disabled={!etudiantId || valeur === ''}>Ajouter la note</Bouton>
          </div>
        </form>
      ) : null}

      {chargement ? <p>Chargement…</p> : notes.length ? (
        <ul className="pv-notes__liste">
          {notes.map((n) => (
            <li key={n.id} className="pv-notes__ligne">
              <span>{n.etudiant?.matricule} — {n.etudiant?.nom} {n.etudiant?.prenom}</span>
              <span className="pv-notes__valeur">{n.valeur}/20</span>
              <Badge teinte={n.etat_validation === 'valide' ? 'success' : 'neutre'}>{n.etat_validation}</Badge>
              {n.motif_penalite ? <Badge teinte="danger">{n.motif_penalite}</Badge> : null}
            </li>
          ))}
        </ul>
      ) : <p>Aucune note saisie pour l'instant.</p>}
    </div>
  );
}
