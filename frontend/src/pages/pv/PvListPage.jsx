import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import { listerPv } from '../../api/pv';
import { listerFilieres } from '../../api/referentiels';
import { Tableau } from '../../components/ui/Tableau';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { libelleStatut, teinteStatut, STATUTS_PV } from './statuts';
import PvImportModal from './PvImportModal';
import '../referentiels/referentiels.css';
import './pv.css';

export default function PvListPage({ statutFixe, titre, description }) {
  const { utilisateur } = useAuth();
  const navigate = useNavigate();
  const peutImporter = utilisateur.role === 'agent_scolarite' && !statutFixe;

  const [donnees, setDonnees] = useState({ donnees: [], total: 0, page: 1, dernieres_pages: 1 });
  const [filieres, setFilieres] = useState([]);
  const [statut, setStatut] = useState(statutFixe ?? '');
  const [filiereId, setFiliereId] = useState('');
  const [page, setPage] = useState(1);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [modaleImportOuverte, setModaleImportOuverte] = useState(false);

  useEffect(() => { listerFilieres().then(setFilieres).catch(() => {}); }, []);

  useEffect(() => {
    charger();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [statut, filiereId, page]);

  function charger() {
    setChargement(true);
    listerPv({ statut, filiere_id: filiereId, page })
      .then(setDonnees)
      .catch(() => setErreur('Impossible de charger les procès-verbaux.'))
      .finally(() => setChargement(false));
  }

  const colonnes = [
    { cle: 'nom_fichier', entete: 'Fichier' },
    { cle: 'code_matiere', entete: 'Matière' },
    { cle: 'filiere', entete: 'Filière', rendu: (pv) => pv.filiere?.nom ?? '—' },
    { cle: 'semestre', entete: 'Semestre / Année', rendu: (pv) => `${pv.semestre} · ${pv.annee_academique}` },
    { cle: 'statut', entete: 'Statut', rendu: (pv) => <Badge teinte={teinteStatut(pv.statut)}>{libelleStatut(pv.statut)}</Badge> },
    { cle: 'depose_par', entete: 'Déposé par', rendu: (pv) => pv.depose_par ? `${pv.depose_par.prenom} ${pv.depose_par.nom}` : '—' },
  ];

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Procès-verbaux</p>
        <h1>{titre ?? 'Suivi des PV'}</h1>
        <p>{description ?? "Import, prétraitement et extraction OCR sont déclenchés automatiquement à l'import."}</p>
      </div>

      <div className="section-entete">
        <div className="filtres">
          {!statutFixe ? (
            <Select label="Statut" value={statut} onChange={(e) => { setPage(1); setStatut(e.target.value); }}>
              <option value="">Tous</option>
              {Object.entries(STATUTS_PV).map(([valeur, { libelle }]) => <option key={valeur} value={valeur}>{libelle}</option>)}
            </Select>
          ) : null}
          <Select label="Filière" value={filiereId} onChange={(e) => { setPage(1); setFiliereId(e.target.value); }}>
            <option value="">Toutes</option>
            {filieres.map((f) => <option key={f.id} value={f.id}>{f.nom}</option>)}
          </Select>
        </div>
        {peutImporter ? <Bouton onClick={() => setModaleImportOuverte(true)}>Importer des PV</Bouton> : null}
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <>
          <Tableau
            colonnes={colonnes}
            lignes={donnees.donnees}
            cleLigne="id"
            surLigneClic={(pv) => navigate(`/pv/${pv.id}`)}
            vide="Aucun procès-verbal ne correspond a ces criteres."
          />
          {donnees.dernieres_pages > 1 ? (
            <div className="pagination">
              <Bouton variante="secondaire" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Précédent</Bouton>
              <span>Page {donnees.page} / {donnees.dernieres_pages} ({donnees.total} PV)</span>
              <Bouton variante="secondaire" disabled={page >= donnees.dernieres_pages} onClick={() => setPage((p) => p + 1)}>Suivant</Bouton>
            </div>
          ) : null}
        </>
      )}

      {modaleImportOuverte ? (
        <PvImportModal
          filieres={filieres}
          onFermer={() => setModaleImportOuverte(false)}
          onTermine={() => { setModaleImportOuverte(false); charger(); }}
        />
      ) : null}
    </div>
  );
}
