import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { listerMesNotes } from '../../api/portail';
import { Tableau } from '../../components/ui/Tableau';
import { Badge } from '../../components/ui/Badge';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import '../pages.css';

export default function MesNotesPage() {
  const navigate = useNavigate();
  const [notes, setNotes] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);

  useEffect(() => {
    listerMesNotes()
      .then(setNotes)
      .catch(() => setErreur('Impossible de charger vos notes.'))
      .finally(() => setChargement(false));
  }, []);

  function reclamerSurCetteNote(note) {
    navigate('/mes-reclamations', { state: { noteId: note.id, descriptionNote: `${note.code_matiere} (${note.semestre}, ${note.annee_academique}) — ${note.valeur}/20` } });
  }

  const colonnes = [
    { cle: 'code_matiere', entete: 'Matière' },
    { cle: 'semestre', entete: 'Semestre / Année', rendu: (n) => `${n.semestre} · ${n.annee_academique}` },
    { cle: 'valeur', entete: 'Note', rendu: (n) => <strong>{n.valeur}/20</strong> },
    { cle: 'coefficient', entete: 'Coefficient' },
    { cle: 'credit', entete: 'Crédits' },
    {
      cle: 'motif_penalite',
      entete: 'Observation',
      rendu: (n) => n.motif_penalite ? <Badge teinte="danger">{n.motif_penalite}</Badge> : '—',
    },
    {
      cle: 'actions',
      entete: '',
      rendu: (n) => <Bouton type="button" variante="secondaire" onClick={() => reclamerSurCetteNote(n)}>Réclamer</Bouton>,
    },
  ];

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Mon espace</p>
        <h1>Mes notes</h1>
        <p>Seules les notes des procès-verbaux publiés apparaissent ici.</p>
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <Tableau colonnes={colonnes} lignes={notes} cleLigne="id" vide="Aucune note publiée pour l'instant." />
      )}
    </div>
  );
}
