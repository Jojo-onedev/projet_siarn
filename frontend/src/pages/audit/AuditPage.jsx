import { useEffect, useState } from 'react';
import { listerAudit } from '../../api/audit';
import { Tableau } from '../../components/ui/Tableau';
import { Champ } from '../../components/ui/Champ';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Modale } from '../../components/ui/Modale';
import '../referentiels/referentiels.css';
import './audit.css';

const CIBLES = ['proces_verbal', 'note', 'utilisateur', 'filiere', 'module', 'etudiant', 'reclamation'];

export default function AuditPage() {
  const [donnees, setDonnees] = useState({ donnees: [], total: 0, page: 1, dernieres_pages: 1 });
  const [action, setAction] = useState('');
  const [cibleType, setCibleType] = useState('');
  const [dateDebut, setDateDebut] = useState('');
  const [dateFin, setDateFin] = useState('');
  const [page, setPage] = useState(1);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [detail, setDetail] = useState(null);

  useEffect(() => {
    setChargement(true);
    listerAudit({ action, cible_type: cibleType, date_debut: dateDebut, date_fin: dateFin, page })
      .then(setDonnees)
      .catch(() => setErreur('Impossible de charger le journal d\'audit.'))
      .finally(() => setChargement(false));
  }, [action, cibleType, dateDebut, dateFin, page]);

  const colonnes = [
    { cle: 'date_heure', entete: 'Date', rendu: (e) => new Date(e.date_heure).toLocaleString('fr-FR') },
    { cle: 'action', entete: 'Action' },
    { cle: 'acteur', entete: 'Acteur', rendu: (e) => e.acteur ? `${e.acteur.prenom} ${e.acteur.nom} (${e.acteur.role})` : 'Système' },
    { cle: 'cible_type', entete: 'Cible', rendu: (e) => e.cible_type ?? '—' },
  ];

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Sécurité et conformité</p>
        <h1>Journal d'audit</h1>
        <p>Piste d'audit append-only : chaque action sensible et chaque transition d'état y est journalisée.</p>
      </div>

      <div className="section-entete">
        <div className="filtres">
          <Champ label="Action (contient)" value={action} onChange={(e) => { setPage(1); setAction(e.target.value); }} placeholder="ex. pv.transition" />
          <Select label="Type de cible" value={cibleType} onChange={(e) => { setPage(1); setCibleType(e.target.value); }}>
            <option value="">Tous</option>
            {CIBLES.map((c) => <option key={c} value={c}>{c}</option>)}
          </Select>
          <Champ label="Depuis" type="date" value={dateDebut} onChange={(e) => { setPage(1); setDateDebut(e.target.value); }} />
          <Champ label="Jusqu'à" type="date" value={dateFin} onChange={(e) => { setPage(1); setDateFin(e.target.value); }} />
        </div>
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <>
          <Tableau
            colonnes={colonnes}
            lignes={donnees.donnees}
            cleLigne="id"
            surLigneClic={(e) => setDetail(e)}
            vide="Aucune entrée ne correspond a ces criteres."
          />
          {donnees.dernieres_pages > 1 ? (
            <div className="pagination">
              <Bouton variante="secondaire" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Précédent</Bouton>
              <span>Page {donnees.page} / {donnees.dernieres_pages} ({donnees.total} entrées)</span>
              <Bouton variante="secondaire" disabled={page >= donnees.dernieres_pages} onClick={() => setPage((p) => p + 1)}>Suivant</Bouton>
            </div>
          ) : null}
        </>
      )}

      {detail ? (
        <Modale titre={detail.action} onFermer={() => setDetail(null)}>
          <p><strong>Acteur :</strong> {detail.acteur ? `${detail.acteur.prenom} ${detail.acteur.nom}` : 'Système'}</p>
          <p><strong>Cible :</strong> {detail.cible_type ?? '—'} {detail.cible_id ?? ''}</p>
          <p><strong>Date :</strong> {new Date(detail.date_heure).toLocaleString('fr-FR')}</p>
          <p><strong>Détails :</strong></p>
          <pre className="audit-detail__json">{JSON.stringify(detail.details, null, 2)}</pre>
        </Modale>
      ) : null}
    </div>
  );
}
