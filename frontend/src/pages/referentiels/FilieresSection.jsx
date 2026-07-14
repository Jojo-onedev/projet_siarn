import { useEffect, useState } from 'react';
import { useAuth } from '../../auth/AuthContext';
import { listerFilieres, creerFiliere, modifierFiliere } from '../../api/referentiels';
import { listerUtilisateurs } from '../../api/utilisateurs';
import { ErreurApi } from '../../api/client';
import { Tableau } from '../../components/ui/Tableau';
import { Modale } from '../../components/ui/Modale';
import { Champ } from '../../components/ui/Champ';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import './referentiels.css';

const ROLES_CHEFS = ['chef_departement', 'responsable_academique'];

export default function FilieresSection() {
  const { utilisateur } = useAuth();
  const peutEcrire = ['agent_scolarite', 'admin'].includes(utilisateur.role);

  const [filieres, setFilieres] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [modaleOuverte, setModaleOuverte] = useState(false);
  const [filiereEnEdition, setFiliereEnEdition] = useState(null);
  const [chefsDisponibles, setChefsDisponibles] = useState([]);

  useEffect(() => {
    chargerFilieres();
    if (utilisateur.role === 'admin') {
      listerUtilisateurs()
        .then((tous) => setChefsDisponibles(tous.filter((u) => ROLES_CHEFS.includes(u.role))))
        .catch(() => {});
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function chargerFilieres() {
    setChargement(true);
    listerFilieres()
      .then(setFilieres)
      .catch(() => setErreur('Impossible de charger les filières.'))
      .finally(() => setChargement(false));
  }

  function ouvrirCreation() {
    setFiliereEnEdition(null);
    setModaleOuverte(true);
  }

  function ouvrirEdition(filiere) {
    setFiliereEnEdition(filiere);
    setModaleOuverte(true);
  }

  async function gererEnregistrement(donnees) {
    if (filiereEnEdition) {
      await modifierFiliere(filiereEnEdition.id, donnees);
    } else {
      await creerFiliere(donnees);
    }
    setModaleOuverte(false);
    chargerFilieres();
  }

  const colonnes = [
    { cle: 'nom', entete: 'Nom' },
    { cle: 'code', entete: 'Code' },
    {
      cle: 'chef_departement',
      entete: 'Chef de département',
      rendu: (f) => (f.chef_departement ? `${f.chef_departement.prenom} ${f.chef_departement.nom}` : '—'),
    },
    { cle: 'actif', entete: 'Statut', rendu: (f) => <Badge teinte={f.actif ? 'success' : 'neutre'}>{f.actif ? 'Active' : 'Inactive'}</Badge> },
  ];

  return (
    <div>
      <div className="section-entete">
        {peutEcrire ? <Bouton onClick={ouvrirCreation}>Nouvelle filière</Bouton> : null}
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <Tableau
          colonnes={colonnes}
          lignes={filieres}
          cleLigne="id"
          surLigneClic={peutEcrire ? ouvrirEdition : undefined}
          vide="Aucune filière enregistrée pour le moment."
        />
      )}

      {modaleOuverte ? (
        <FormulaireFiliere
          filiere={filiereEnEdition}
          chefsDisponibles={chefsDisponibles}
          estAdmin={utilisateur.role === 'admin'}
          onAnnuler={() => setModaleOuverte(false)}
          onEnregistrer={gererEnregistrement}
        />
      ) : null}
    </div>
  );
}

function FormulaireFiliere({ filiere, chefsDisponibles, estAdmin, onAnnuler, onEnregistrer }) {
  const [nom, setNom] = useState(filiere?.nom ?? '');
  const [code, setCode] = useState(filiere?.code ?? '');
  const [chefId, setChefId] = useState(filiere?.chef_departement?.id ?? '');
  const [erreurs, setErreurs] = useState({});
  const [erreurGenerale, setErreurGenerale] = useState(null);
  const [enCours, setEnCours] = useState(false);

  async function gererEnvoi(e) {
    e.preventDefault();
    setErreurs({});
    setErreurGenerale(null);
    setEnCours(true);
    try {
      await onEnregistrer({ nom, code, chef_departement_id: chefId || null });
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
    <Modale titre={filiere ? 'Modifier la filière' : 'Nouvelle filière'} onFermer={onAnnuler}>
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreurGenerale ? <Alerte type="erreur">{erreurGenerale}</Alerte> : null}
        <Champ label="Nom" required value={nom} onChange={(e) => setNom(e.target.value)} erreur={erreurs.nom} />
        <Champ label="Code" required value={code} onChange={(e) => setCode(e.target.value)} erreur={erreurs.code} aide="Identifiant court, unique (ex. GL, RT, GC)." />
        {estAdmin ? (
          <Select label="Chef de département (optionnel)" value={chefId} onChange={(e) => setChefId(e.target.value)} erreur={erreurs.chef_departement_id}>
            <option value="">— Aucun pour l'instant —</option>
            {chefsDisponibles.map((u) => (
              <option key={u.id} value={u.id}>{u.prenom} {u.nom} ({u.role})</option>
            ))}
          </Select>
        ) : null}
        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onAnnuler}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours}>Enregistrer</Bouton>
        </div>
      </form>
    </Modale>
  );
}
