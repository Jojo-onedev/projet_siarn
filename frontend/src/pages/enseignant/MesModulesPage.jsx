import { useEffect, useState } from 'react';
import { listerMesModules, listerNotesDuModule, signalerFraude } from '../../api/enseignant';
import { Tableau } from '../../components/ui/Tableau';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { Modale } from '../../components/ui/Modale';
import { ErreurApi } from '../../api/client';
import { libelleStatut, teinteStatut } from '../pv/statuts';
import '../pages.css';
import '../referentiels/referentiels.css';

export default function MesModulesPage() {
  const [modules, setModules] = useState([]);
  const [moduleOuvert, setModuleOuvert] = useState(null);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);

  useEffect(() => {
    listerMesModules().then(setModules).catch(() => setErreur('Impossible de charger vos modules.')).finally(() => setChargement(false));
  }, []);

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Mon enseignement</p>
        <h1>Mes modules</h1>
        <p>Vérifiez les notes extraites de vos procès-verbaux et signalez une anomalie si une valeur ne correspond pas à ce que vous avez attribué.</p>
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : modules.length === 0 ? (
        <Alerte type="info">Aucun module ne vous est actuellement rattaché comme enseignant référent.</Alerte>
      ) : (
        <div className="grille-cartes">
          {modules.map((m) => (
            <button key={m.id} type="button" className="carte-lien" style={{ textAlign: 'left', width: '100%', border: 'none', cursor: 'pointer' }} onClick={() => setModuleOuvert(m)}>
              <h2>{m.code} — {m.nom}</h2>
              <p>{m.filiere?.nom} · {m.niveau} · {m.semestre}</p>
            </button>
          ))}
        </div>
      )}

      {moduleOuvert ? (
        <NotesDuModuleModale module={moduleOuvert} onFermer={() => setModuleOuvert(null)} />
      ) : null}
    </div>
  );
}

function NotesDuModuleModale({ module, onFermer }) {
  const [notes, setNotes] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [noteASignaler, setNoteASignaler] = useState(null);

  useEffect(() => { charger(); }, []);

  function charger() {
    setChargement(true);
    listerNotesDuModule(module.id).then(setNotes).catch(() => setErreur('Impossible de charger les notes de ce module.')).finally(() => setChargement(false));
  }

  const colonnes = [
    { cle: 'etudiant', entete: 'Étudiant', rendu: (n) => n.etudiant ? `${n.etudiant.matricule} — ${n.etudiant.nom} ${n.etudiant.prenom}` : '—' },
    { cle: 'valeur', entete: 'Note', rendu: (n) => `${n.valeur}/20` },
    { cle: 'pv_statut', entete: 'Statut du PV', rendu: (n) => n.pv ? <Badge teinte={teinteStatut(n.pv.statut)}>{libelleStatut(n.pv.statut)}</Badge> : '—' },
    { cle: 'motif_penalite', entete: 'Observation', rendu: (n) => n.motif_penalite ? <Badge teinte="danger">{n.motif_penalite}</Badge> : '—' },
    { cle: 'actions', entete: '', rendu: (n) => (
      <Bouton type="button" variante="secondaire" onClick={() => setNoteASignaler(n)}>Signaler une fraude</Bouton>
    ) },
  ];

  return (
    <Modale titre={`Notes — ${module.code}`} onFermer={onFermer} largeur="800px">
      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <Tableau colonnes={colonnes} lignes={notes} cleLigne="id" vide="Aucune note pour ce module pour l'instant." />
      )}

      {noteASignaler ? (
        <FormulaireSignalerFraude
          note={noteASignaler}
          onFermer={() => setNoteASignaler(null)}
          onTermine={() => { setNoteASignaler(null); charger(); }}
        />
      ) : null}
    </Modale>
  );
}

function FormulaireSignalerFraude({ note, onFermer, onTermine }) {
  const [motif, setMotif] = useState('');
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function gererEnvoi(e) {
    e.preventDefault();
    setErreur(null);
    setEnCours(true);
    try {
      await signalerFraude(note.id, motif);
      onTermine();
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Envoi impossible.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <Modale titre="Signaler une fraude" onFermer={onFermer}>
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
        <Alerte type="avertissement">
          Cette action applique automatiquement une pénalité (00/20) sur la note de {note.etudiant?.prenom} {note.etudiant?.nom}, avec le motif tracé ci-dessous.
        </Alerte>
        <div className="champ">
          <label className="champ__label" htmlFor="motif-fraude">Motif</label>
          <textarea
            id="motif-fraude"
            className="champ__input"
            rows={4}
            required
            maxLength={1000}
            value={motif}
            onChange={(e) => setMotif(e.target.value)}
          />
        </div>
        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onFermer}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours} disabled={!motif.trim()}>Confirmer le signalement</Bouton>
        </div>
      </form>
    </Modale>
  );
}
