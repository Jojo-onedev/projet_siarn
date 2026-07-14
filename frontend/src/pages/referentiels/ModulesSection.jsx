import { useEffect, useState } from 'react';
import { useAuth } from '../../auth/AuthContext';
import { listerFilieres, listerModules, creerModule, modifierModule } from '../../api/referentiels';
import { listerUtilisateurs } from '../../api/utilisateurs';
import { ErreurApi } from '../../api/client';
import { Tableau } from '../../components/ui/Tableau';
import { Modale } from '../../components/ui/Modale';
import { Champ } from '../../components/ui/Champ';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { NIVEAUX, SEMESTRES } from './constantes';
import './referentiels.css';

export default function ModulesSection() {
  const { utilisateur } = useAuth();
  const peutEcrire = ['agent_scolarite', 'admin'].includes(utilisateur.role);

  const [modules, setModules] = useState([]);
  const [filieres, setFilieres] = useState([]);
  const [enseignants, setEnseignants] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [modaleOuverte, setModaleOuverte] = useState(false);
  const [moduleEnEdition, setModuleEnEdition] = useState(null);

  useEffect(() => {
    Promise.all([listerModules(), listerFilieres()])
      .then(([m, f]) => { setModules(m); setFilieres(f); })
      .catch(() => setErreur('Impossible de charger les modules.'))
      .finally(() => setChargement(false));
    if (utilisateur.role === 'admin') {
      listerUtilisateurs()
        .then((tous) => setEnseignants(tous.filter((u) => u.role === 'enseignant')))
        .catch(() => {});
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function rechargerModules() {
    listerModules().then(setModules).catch(() => setErreur('Impossible de charger les modules.'));
  }

  async function gererEnregistrement(donnees) {
    if (moduleEnEdition) {
      await modifierModule(moduleEnEdition.id, donnees);
    } else {
      await creerModule(donnees);
    }
    setModaleOuverte(false);
    rechargerModules();
  }

  const colonnes = [
    { cle: 'code', entete: 'Code' },
    { cle: 'nom', entete: 'Nom' },
    { cle: 'filiere', entete: 'Filière', rendu: (m) => m.filiere?.nom ?? '—' },
    { cle: 'niveau', entete: 'Niveau' },
    { cle: 'semestre', entete: 'Semestre' },
    { cle: 'coefficient', entete: 'Coeff.' },
    { cle: 'credits', entete: 'Crédits' },
    { cle: 'actif', entete: 'Statut', rendu: (m) => <Badge teinte={m.actif ? 'success' : 'neutre'}>{m.actif ? 'Actif' : 'Inactif'}</Badge> },
  ];

  return (
    <div>
      <div className="section-entete">
        {peutEcrire ? (
          <Bouton onClick={() => { setModuleEnEdition(null); setModaleOuverte(true); }}>Nouveau module</Bouton>
        ) : null}
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <Tableau
          colonnes={colonnes}
          lignes={modules}
          cleLigne="id"
          surLigneClic={peutEcrire ? (m) => { setModuleEnEdition(m); setModaleOuverte(true); } : undefined}
          vide="Aucun module enregistré pour le moment."
        />
      )}

      {modaleOuverte ? (
        <FormulaireModule
          module={moduleEnEdition}
          filieres={filieres}
          enseignants={enseignants}
          estAdmin={utilisateur.role === 'admin'}
          onAnnuler={() => setModaleOuverte(false)}
          onEnregistrer={gererEnregistrement}
        />
      ) : null}
    </div>
  );
}

function FormulaireModule({ module, filieres, enseignants, estAdmin, onAnnuler, onEnregistrer }) {
  const [code, setCode] = useState(module?.code ?? '');
  const [nom, setNom] = useState(module?.nom ?? '');
  const [filiereId, setFiliereId] = useState(module?.filiere?.id ?? '');
  const [niveau, setNiveau] = useState(module?.niveau ?? NIVEAUX[0]);
  const [semestre, setSemestre] = useState(module?.semestre ?? SEMESTRES[0]);
  const [coefficient, setCoefficient] = useState(module?.coefficient ?? 1);
  const [credits, setCredits] = useState(module?.credits ?? 1);
  const [enseignantId, setEnseignantId] = useState(module?.enseignant_id ?? '');
  const [actif, setActif] = useState(module?.actif ?? true);
  const [erreurs, setErreurs] = useState({});
  const [erreurGenerale, setErreurGenerale] = useState(null);
  const [enCours, setEnCours] = useState(false);

  async function gererEnvoi(e) {
    e.preventDefault();
    setErreurs({});
    setErreurGenerale(null);
    setEnCours(true);
    try {
      const payload = {
        code, nom, filiere_id: filiereId, niveau, semestre,
        coefficient: Number(coefficient), credits: Number(credits),
        enseignant_id: enseignantId || null,
      };
      if (module) payload.actif = actif;
      await onEnregistrer(payload);
    } catch (err) {
      if (err instanceof ErreurApi && err.erreurs) {
        setErreurs(Object.fromEntries(Object.entries(err.erreurs).map(([k, v]) => [k, v[0]])));
      } else if (err instanceof ErreurApi) {
        setErreurGenerale(err.message);
      } else {
        setErreurGenerale('Une erreur inattendue est survenue.');
      }
    } finally {
      setEnCours(false);
    }
  }

  return (
    <Modale titre={module ? 'Modifier le module' : 'Nouveau module'} onFermer={onAnnuler} largeur="640px">
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreurGenerale ? <Alerte type="erreur">{erreurGenerale}</Alerte> : null}
        <div className="formulaire__grille">
          <Champ label="Code" required value={code} onChange={(e) => setCode(e.target.value)} erreur={erreurs.code} />
          <Champ label="Nom" required value={nom} onChange={(e) => setNom(e.target.value)} erreur={erreurs.nom} />
        </div>
        <Select label="Filière" required value={filiereId} onChange={(e) => setFiliereId(e.target.value)} erreur={erreurs.filiere_id}>
          <option value="" disabled>Sélectionner une filière</option>
          {filieres.map((f) => <option key={f.id} value={f.id}>{f.nom}</option>)}
        </Select>
        <div className="formulaire__grille">
          <Select label="Niveau" value={niveau} onChange={(e) => setNiveau(e.target.value)} erreur={erreurs.niveau}>
            {NIVEAUX.map((n) => <option key={n} value={n}>{n}</option>)}
          </Select>
          <Select label="Semestre" value={semestre} onChange={(e) => setSemestre(e.target.value)} erreur={erreurs.semestre}>
            {SEMESTRES.map((s) => <option key={s} value={s}>{s}</option>)}
          </Select>
        </div>
        <div className="formulaire__grille">
          <Champ label="Coefficient" type="number" min="0" step="0.5" value={coefficient} onChange={(e) => setCoefficient(e.target.value)} erreur={erreurs.coefficient} />
          <Champ label="Crédits" type="number" min="0" step="1" value={credits} onChange={(e) => setCredits(e.target.value)} erreur={erreurs.credits} />
        </div>
        {estAdmin ? (
          <Select label="Enseignant référent (optionnel)" value={enseignantId} onChange={(e) => setEnseignantId(e.target.value)} erreur={erreurs.enseignant_id}>
            <option value="">— Aucun pour l'instant —</option>
            {enseignants.map((u) => <option key={u.id} value={u.id}>{u.prenom} {u.nom}</option>)}
          </Select>
        ) : null}
        {module ? (
          <label className="bloc-compte__case">
            <input type="checkbox" checked={actif} onChange={(e) => setActif(e.target.checked)} />
            <span>Module actif</span>
          </label>
        ) : null}
        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onAnnuler}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours}>Enregistrer</Bouton>
        </div>
      </form>
    </Modale>
  );
}
