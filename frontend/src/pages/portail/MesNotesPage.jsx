import { useEffect, useState } from 'react';
import { listerMesNotes } from '../../api/portail';
import { Tableau } from '../../components/ui/Tableau';
import { Badge } from '../../components/ui/Badge';
import { Alerte } from '../../components/ui/Alerte';
import '../pages.css';

export default function MesNotesPage() {
  const [notes, setNotes] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);

  useEffect(() => {
    listerMesNotes()
      .then((r) => setNotes(r.map((n, i) => ({ ...n, _cle: `${n.code_matiere}-${n.semestre}-${n.annee_academique}-${i}` }))))
      .catch(() => setErreur('Impossible de charger vos notes.'))
      .finally(() => setChargement(false));
  }, []);

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
        <Tableau colonnes={colonnes} lignes={notes} cleLigne="_cle" vide="Aucune note publiée pour l'instant." />
      )}
    </div>
  );
}
