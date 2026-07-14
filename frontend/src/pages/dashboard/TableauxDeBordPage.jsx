import { useEffect, useState } from 'react';
import { useAuth } from '../../auth/AuthContext';
import { obtenirDashboardPv, obtenirDashboardOcr, exporterPvCsv } from '../../api/dashboard';
import { listerFilieres } from '../../api/referentiels';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { libelleStatut, teinteStatut } from '../pv/statuts';
import { SEMESTRES, anneesAcademiques } from '../referentiels/constantes';
import '../pages.css';
import './dashboard.css';

export default function TableauxDeBordPage() {
  const { utilisateur } = useAuth();
  const peutFiltrerFiliere = utilisateur.role !== 'chef_departement';

  const [filieres, setFilieres] = useState([]);
  const [filiereId, setFiliereId] = useState('');
  const [semestre, setSemestre] = useState('');
  const [anneeAcademique, setAnneeAcademique] = useState('');
  const [statsPv, setStatsPv] = useState(null);
  const [statsOcr, setStatsOcr] = useState(null);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [exportEnCours, setExportEnCours] = useState(false);

  useEffect(() => {
    if (peutFiltrerFiliere) listerFilieres().then(setFilieres).catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    setChargement(true);
    Promise.all([
      obtenirDashboardPv({ filiere_id: filiereId, semestre, annee_academique: anneeAcademique }),
      obtenirDashboardOcr(),
    ])
      .then(([pv, ocr]) => { setStatsPv(pv); setStatsOcr(ocr); })
      .catch(() => setErreur('Impossible de charger les tableaux de bord.'))
      .finally(() => setChargement(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filiereId, semestre, anneeAcademique]);

  async function gererExport() {
    setExportEnCours(true);
    try {
      const blob = await exporterPvCsv({ filiere_id: filiereId, semestre, annee_academique: anneeAcademique });
      const url = URL.createObjectURL(blob);
      const lien = document.createElement('a');
      lien.href = url;
      lien.download = 'pv_export.csv';
      lien.click();
      URL.revokeObjectURL(url);
    } finally {
      setExportEnCours(false);
    }
  }

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Pilotage</p>
        <h1>Tableaux de bord</h1>
        <p>{peutFiltrerFiliere ? 'Vue consolidée, filtrable par filière.' : 'Vue restreinte à votre filière.'}</p>
      </div>

      <div className="section-entete">
        <div className="filtres">
          {peutFiltrerFiliere ? (
            <Select label="Filière" value={filiereId} onChange={(e) => setFiliereId(e.target.value)}>
              <option value="">Toutes</option>
              {filieres.map((f) => <option key={f.id} value={f.id}>{f.nom}</option>)}
            </Select>
          ) : null}
          <Select label="Semestre" value={semestre} onChange={(e) => setSemestre(e.target.value)}>
            <option value="">Tous</option>
            {SEMESTRES.map((s) => <option key={s} value={s}>{s}</option>)}
          </Select>
          <Select label="Année académique" value={anneeAcademique} onChange={(e) => setAnneeAcademique(e.target.value)}>
            <option value="">Toutes</option>
            {anneesAcademiques().map((a) => <option key={a} value={a}>{a}</option>)}
          </Select>
        </div>
        <Bouton variante="secondaire" onClick={gererExport} chargement={exportEnCours}>Exporter en CSV</Bouton>
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <>
          <div className="grille-cartes tableau-bord__stats">
            <div className="carte-stat">
              <span className="carte-stat__valeur">{statsPv.total_pv}</span>
              <span className="carte-stat__label">Procès-verbaux</span>
            </div>
            <div className="carte-stat">
              <span className="carte-stat__valeur">{statsPv.delai_moyen_traitement_heures ?? '—'}{statsPv.delai_moyen_traitement_heures != null ? 'h' : ''}</span>
              <span className="carte-stat__label">Délai moyen jusqu'à publication</span>
            </div>
            <div className="carte-stat carte-stat--alerte">
              <span className="carte-stat__valeur">{statsPv.alertes_sla_non_lues}</span>
              <span className="carte-stat__label">Alertes SLA non lues</span>
            </div>
          </div>

          <section className="pv-detail__section">
            <h2>Répartition par statut</h2>
            <ul className="tableau-bord__repartition">
              {Object.entries(statsPv.par_statut ?? {}).map(([statut, total]) => (
                <li key={statut}>
                  <Badge teinte={teinteStatut(statut)}>{libelleStatut(statut)}</Badge>
                  <span>{total}</span>
                </li>
              ))}
            </ul>
          </section>

          <section className="pv-detail__section">
            <h2>Modèle OCR</h2>
            <p>
              Confiance moyenne en production : {statsOcr.confiance_moyenne_production != null ? `${Math.round(statsOcr.confiance_moyenne_production * 100)}%` : '—'}
              {' '}({statsOcr.nombre_extractions_analysees} champs analysés)
            </p>
            <ul className="tableau-bord__modeles">
              {statsOcr.modeles.map((m) => (
                <li key={m.id}>
                  <span>{m.version}</span>
                  <Badge teinte={m.statut === 'actif' ? 'success' : m.statut === 'candidat' ? 'info' : 'neutre'}>{m.statut}</Badge>
                  <span>CER {m.cer}% · WER {m.wer}%</span>
                </li>
              ))}
            </ul>
          </section>
        </>
      )}
    </div>
  );
}
