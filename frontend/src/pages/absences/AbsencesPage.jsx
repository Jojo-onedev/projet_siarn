import { useEffect, useState } from 'react';
import { listerAbsences, creerAbsence } from '../../api/absences';
import { listerFilieres, listerModules, listerEtudiants } from '../../api/referentiels';
import { Tableau } from '../../components/ui/Tableau';
import { Select } from '../../components/ui/Select';
import { Champ } from '../../components/ui/Champ';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { Modale } from '../../components/ui/Modale';
import { ErreurApi } from '../../api/client';
import '../pages.css';
import '../referentiels/referentiels.css';

export default function AbsencesPage() {
  const [absences, setAbsences] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [modaleOuverte, setModaleOuverte] = useState(false);

  useEffect(() => { charger(); }, []);

  function charger() {
    setChargement(true);
    listerAbsences().then(setAbsences).catch(() => setErreur('Impossible de charger les absences.')).finally(() => setChargement(false));
  }

  const colonnes = [
    { cle: 'date', entete: 'Date', rendu: (a) => new Date(a.date).toLocaleDateString('fr-FR') },
    { cle: 'etudiant', entete: 'Étudiant', rendu: (a) => a.etudiant?.matricule ?? '—' },
    { cle: 'module', entete: 'Module', rendu: (a) => a.module?.code ?? '—' },
    { cle: 'duree_heures', entete: 'Durée (h)' },
    { cle: 'justifiee', entete: 'Statut', rendu: (a) => <Badge teinte={a.justifiee ? 'success' : 'danger'}>{a.justifiee ? 'Justifiée' : 'Non justifiée'}</Badge> },
  ];

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Assiduité</p>
        <h1>Absences</h1>
        <p>Une absence non justifiée compte dans le cumul déclenchant la pénalité automatique (§7.6) dès que le seuil configuré est dépassé.</p>
      </div>

      <div className="section-entete">
        <span />
        <Bouton onClick={() => setModaleOuverte(true)}>Déclarer une absence</Bouton>
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <Tableau colonnes={colonnes} lignes={absences} cleLigne="id" vide="Aucune absence déclarée pour l'instant." />
      )}

      {modaleOuverte ? (
        <FormulaireAbsence onFermer={() => setModaleOuverte(false)} onTermine={() => { setModaleOuverte(false); charger(); }} />
      ) : null}
    </div>
  );
}

function FormulaireAbsence({ onFermer, onTermine }) {
  const [filieres, setFilieres] = useState([]);
  const [filiereId, setFiliereId] = useState('');
  const [etudiants, setEtudiants] = useState([]);
  const [modules, setModules] = useState([]);
  const [etudiantId, setEtudiantId] = useState('');
  const [moduleId, setModuleId] = useState('');
  const [dureeHeures, setDureeHeures] = useState('2');
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [justifiee, setJustifiee] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [enCours, setEnCours] = useState(false);

  useEffect(() => { listerFilieres().then(setFilieres).catch(() => {}); }, []);

  useEffect(() => {
    if (!filiereId) { setEtudiants([]); setModules([]); return; }
    listerEtudiants({ filiere_id: filiereId, par_page: 500 }).then((r) => setEtudiants(r.donnees)).catch(() => {});
    listerModules(filiereId).then(setModules).catch(() => {});
  }, [filiereId]);

  async function gererEnvoi(e) {
    e.preventDefault();
    setErreur(null);
    setEnCours(true);
    try {
      await creerAbsence({ etudiant_id: etudiantId, module_id: moduleId, duree_heures: Number(dureeHeures), date, justifiee });
      onTermine();
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Enregistrement impossible.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <Modale titre="Déclarer une absence" onFermer={onFermer} largeur="640px">
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}

        <Select label="Filière" required value={filiereId} onChange={(e) => { setFiliereId(e.target.value); setEtudiantId(''); setModuleId(''); }}>
          <option value="" disabled>Sélectionner une filière</option>
          {filieres.map((f) => <option key={f.id} value={f.id}>{f.nom}</option>)}
        </Select>

        <div className="formulaire__grille">
          <Select label="Étudiant" required value={etudiantId} onChange={(e) => setEtudiantId(e.target.value)} disabled={!filiereId}>
            <option value="" disabled>Sélectionner…</option>
            {etudiants.map((e) => <option key={e.id} value={e.id}>{e.matricule} — {e.nom} {e.prenom}</option>)}
          </Select>
          <Select label="Module" required value={moduleId} onChange={(e) => setModuleId(e.target.value)} disabled={!filiereId}>
            <option value="" disabled>Sélectionner…</option>
            {modules.map((m) => <option key={m.id} value={m.id}>{m.code} — {m.nom}</option>)}
          </Select>
        </div>

        <div className="formulaire__grille">
          <Champ label="Durée (heures)" type="number" min="0.5" step="0.5" required value={dureeHeures} onChange={(e) => setDureeHeures(e.target.value)} />
          <Champ label="Date" type="date" required value={date} onChange={(e) => setDate(e.target.value)} />
        </div>

        <label className="bloc-compte__case">
          <input type="checkbox" checked={justifiee} onChange={(e) => setJustifiee(e.target.checked)} />
          <span>Absence justifiée</span>
        </label>

        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onFermer}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours} disabled={!etudiantId || !moduleId}>Enregistrer</Bouton>
        </div>
      </form>
    </Modale>
  );
}
