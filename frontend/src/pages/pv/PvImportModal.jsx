import { useEffect, useState } from 'react';
import { Modale } from '../../components/ui/Modale';
import { Champ } from '../../components/ui/Champ';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { ErreurApi } from '../../api/client';
import { importerPv } from '../../api/pv';
import { listerModules } from '../../api/referentiels';
import { SEMESTRES, anneesAcademiques } from '../referentiels/constantes';
import { libelleStatut, teinteStatut } from './statuts';

export default function PvImportModal({ filieres, onFermer, onTermine }) {
  const [fichiers, setFichiers] = useState([]);
  const [codeMatiere, setCodeMatiere] = useState('');
  const [filiereId, setFiliereId] = useState('');
  const [moduleId, setModuleId] = useState('');
  const [modules, setModules] = useState([]);
  const [semestre, setSemestre] = useState(SEMESTRES[0]);
  const [anneeAcademique, setAnneeAcademique] = useState(anneesAcademiques()[1]);
  const [erreur, setErreur] = useState(null);
  const [enCours, setEnCours] = useState(false);
  const [resultats, setResultats] = useState(null);

  useEffect(() => {
    if (!filiereId) { setModules([]); return; }
    listerModules(filiereId).then(setModules).catch(() => setModules([]));
  }, [filiereId]);

  async function gererEnvoi(e) {
    e.preventDefault();
    if (fichiers.length === 0) return;
    setErreur(null);
    setEnCours(true);
    try {
      const r = await importerPv(fichiers, {
        code_matiere: codeMatiere,
        filiere_id: filiereId,
        module_id: moduleId,
        semestre,
        annee_academique: anneeAcademique,
      });
      setResultats(r.pv_importes);
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Import impossible.');
    } finally {
      setEnCours(false);
    }
  }

  if (resultats) {
    return (
      <Modale titre="Résultat de l'import" onFermer={onTermine} largeur="640px">
        <div className="formulaire">
          <Alerte type="info">{resultats.length} fichier(s) traité(s). Prétraitement et extraction OCR ont été appliqués automatiquement.</Alerte>
          <ul className="pv-import__liste">
            {resultats.map((pv) => (
              <li key={pv.id} className="pv-import__ligne">
                <span>{pv.nom_fichier}</span>
                <Badge teinte={teinteStatut(pv.statut)}>{libelleStatut(pv.statut)}</Badge>
              </li>
            ))}
          </ul>
          <div className="formulaire__actions">
            <Bouton onClick={onTermine}>Fermer</Bouton>
          </div>
        </div>
      </Modale>
    );
  }

  return (
    <Modale titre="Importer des procès-verbaux" onFermer={onFermer} largeur="640px">
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}

        <div className="champ">
          <label className="champ__label" htmlFor="pv-fichiers">Fichiers scannés (JPG/PNG)</label>
          <input
            id="pv-fichiers"
            type="file"
            accept=".jpg,.jpeg,.png"
            multiple
            className="champ__input"
            onChange={(e) => setFichiers(Array.from(e.target.files))}
            required
          />
          <p className="champ__aide">{fichiers.length} fichier(s) sélectionné(s).</p>
        </div>

        <Champ label="Code matière" required value={codeMatiere} onChange={(e) => setCodeMatiere(e.target.value)} />

        <div className="formulaire__grille">
          <Select label="Filière" required value={filiereId} onChange={(e) => { setFiliereId(e.target.value); setModuleId(''); }}>
            <option value="" disabled>Sélectionner une filière</option>
            {filieres.map((f) => <option key={f.id} value={f.id}>{f.nom}</option>)}
          </Select>
          <Select label="Module (optionnel)" value={moduleId} onChange={(e) => setModuleId(e.target.value)} disabled={!filiereId}>
            <option value="">—</option>
            {modules.map((m) => <option key={m.id} value={m.id}>{m.code} — {m.nom}</option>)}
          </Select>
        </div>

        <div className="formulaire__grille">
          <Select label="Semestre" value={semestre} onChange={(e) => setSemestre(e.target.value)}>
            {SEMESTRES.map((s) => <option key={s} value={s}>{s}</option>)}
          </Select>
          <Select label="Année académique" value={anneeAcademique} onChange={(e) => setAnneeAcademique(e.target.value)}>
            {anneesAcademiques().map((a) => <option key={a} value={a}>{a}</option>)}
          </Select>
        </div>

        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onFermer}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours} disabled={fichiers.length === 0 || !filiereId}>Importer</Bouton>
        </div>
      </form>
    </Modale>
  );
}
