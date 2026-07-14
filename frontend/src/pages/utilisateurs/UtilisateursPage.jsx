import { useEffect, useState } from 'react';
import { useAuth } from '../../auth/AuthContext';
import { listerUtilisateurs, creerUtilisateur, modifierUtilisateur } from '../../api/utilisateurs';
import { Tableau } from '../../components/ui/Tableau';
import { Champ } from '../../components/ui/Champ';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { Modale } from '../../components/ui/Modale';
import { ErreurApi } from '../../api/client';
import { genererMotDePasse } from '../referentiels/generateurs';
import '../referentiels/referentiels.css';

const ROLES = [
  { valeur: 'agent_scolarite', libelle: 'Agent de scolarité' },
  { valeur: 'enseignant', libelle: 'Enseignant' },
  { valeur: 'chef_departement', libelle: 'Chef de département' },
  { valeur: 'responsable_academique', libelle: 'Responsable académique' },
  { valeur: 'etudiant', libelle: 'Étudiant' },
  { valeur: 'admin', libelle: 'Administrateur' },
  { valeur: 'directeur', libelle: 'Directeur' },
];

export default function UtilisateursPage() {
  const { utilisateur: moi } = useAuth();
  const [utilisateurs, setUtilisateurs] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [modaleOuverte, setModaleOuverte] = useState(false);
  const [enCoursId, setEnCoursId] = useState(null);
  const [reinitialisationCible, setReinitialisationCible] = useState(null);

  useEffect(() => { charger(); }, []);

  function charger() {
    setChargement(true);
    listerUtilisateurs().then(setUtilisateurs).catch(() => setErreur('Impossible de charger les utilisateurs.')).finally(() => setChargement(false));
  }

  async function basculerActivation(u) {
    setErreur(null);
    setEnCoursId(u.id);
    try {
      await modifierUtilisateur(u.id, { actif: !u.actif });
      charger();
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Action impossible.');
    } finally {
      setEnCoursId(null);
    }
  }

  async function reinitialiserMfa(u) {
    setErreur(null);
    setEnCoursId(u.id);
    try {
      await modifierUtilisateur(u.id, { reinitialiser_mfa: true });
      charger();
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Action impossible.');
    } finally {
      setEnCoursId(null);
    }
  }

  const colonnes = [
    { cle: 'nom', entete: 'Nom', rendu: (u) => `${u.prenom} ${u.nom}` },
    { cle: 'email', entete: 'E-mail' },
    { cle: 'role', entete: 'Rôle', rendu: (u) => ROLES.find((r) => r.valeur === u.role)?.libelle ?? u.role },
    { cle: 'statut_mfa', entete: 'MFA', rendu: (u) => <Badge teinte={u.statut_mfa ? 'success' : 'neutre'}>{u.statut_mfa ? 'Actif' : 'Non configuré'}</Badge> },
    { cle: 'actif', entete: 'Compte', rendu: (u) => <Badge teinte={u.actif ? 'success' : 'danger'}>{u.actif ? 'Actif' : 'Désactivé'}</Badge> },
    {
      cle: 'actions',
      entete: '',
      rendu: (u) => u.id === moi.id ? null : (
        <div className="section-entete__actions">
          <Bouton
            type="button"
            variante="secondaire"
            chargement={enCoursId === u.id}
            onClick={() => basculerActivation(u)}
          >
            {u.actif ? 'Désactiver' : 'Réactiver'}
          </Bouton>
          <Bouton type="button" variante="secondaire" onClick={() => setReinitialisationCible(u)}>
            Réinitialiser mot de passe
          </Bouton>
          {u.statut_mfa ? (
            <Bouton type="button" variante="secondaire" chargement={enCoursId === u.id} onClick={() => reinitialiserMfa(u)}>
              Réinitialiser MFA
            </Bouton>
          ) : null}
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Administration</p>
        <h1>Comptes utilisateurs</h1>
        <p>Un compte ayant un historique d'audit ne peut jamais être supprimé, seulement désactivé — la désactivation prend effet immédiatement, même si une session était déjà ouverte.</p>
      </div>

      <div className="section-entete">
        <span />
        <Bouton onClick={() => setModaleOuverte(true)}>Nouvel utilisateur</Bouton>
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <Tableau colonnes={colonnes} lignes={utilisateurs} cleLigne="id" vide="Aucun utilisateur." />
      )}

      {modaleOuverte ? (
        <FormulaireUtilisateur onFermer={() => setModaleOuverte(false)} onTermine={() => { setModaleOuverte(false); charger(); }} />
      ) : null}

      {reinitialisationCible ? (
        <ModaleReinitialisationMotDePasse
          utilisateur={reinitialisationCible}
          onFermer={() => setReinitialisationCible(null)}
        />
      ) : null}
    </div>
  );
}

function ModaleReinitialisationMotDePasse({ utilisateur, onFermer }) {
  const [motDePasse, setMotDePasse] = useState(() => genererMotDePasse());
  const [motDePasseVisible, setMotDePasseVisible] = useState(true);
  const [copie, setCopie] = useState(false);
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [succes, setSucces] = useState(false);

  async function copier() {
    try {
      await navigator.clipboard.writeText(motDePasse);
      setCopie(true);
      setTimeout(() => setCopie(false), 2000);
    } catch {
      // Presse-papiers indisponible : le mot de passe reste lisible a l'ecran.
    }
  }

  async function confirmer() {
    setErreur(null);
    setEnCours(true);
    try {
      await modifierUtilisateur(utilisateur.id, { nouveau_mot_de_passe: motDePasse });
      setSucces(true);
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Action impossible.');
    } finally {
      setEnCours(false);
    }
  }

  if (succes) {
    return (
      <Modale titre="Mot de passe réinitialisé" onFermer={onFermer}>
        <div className="formulaire">
          <Alerte type="succes" titre={`${utilisateur.prenom} ${utilisateur.nom}`}>
            Communiquez ce mot de passe à l'utilisateur — il ne sera plus jamais affiché après fermeture de cette fenêtre.
          </Alerte>
          <div className="auth-secret">
            <span className="champ__label">Nouveau mot de passe</span>
            <code className="auth-secret__valeur">{motDePasse}</code>
            <Bouton type="button" variante="secondaire" onClick={copier}>{copie ? 'Copié !' : 'Copier'}</Bouton>
          </div>
          <div className="formulaire__actions">
            <Bouton onClick={onFermer}>Terminé</Bouton>
          </div>
        </div>
      </Modale>
    );
  }

  return (
    <Modale titre={`Réinitialiser le mot de passe de ${utilisateur.prenom} ${utilisateur.nom}`} onFermer={onFermer}>
      <div className="formulaire">
        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
        <div className="champ-avec-bouton">
          <Champ
            label="Nouveau mot de passe"
            type={motDePasseVisible ? 'text' : 'password'}
            value={motDePasse}
            onChange={(e) => setMotDePasse(e.target.value)}
            minLength={12}
          />
          <Bouton type="button" variante="secondaire" onClick={() => setMotDePasseVisible((v) => !v)}>
            {motDePasseVisible ? 'Masquer' : 'Afficher'}
          </Bouton>
          <Bouton type="button" variante="secondaire" onClick={() => setMotDePasse(genererMotDePasse())}>Générer</Bouton>
        </div>
        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onFermer}>Annuler</Bouton>
          <Bouton type="button" chargement={enCours} onClick={confirmer}>Réinitialiser</Bouton>
        </div>
      </div>
    </Modale>
  );
}

function FormulaireUtilisateur({ onFermer, onTermine }) {
  const [nom, setNom] = useState('');
  const [prenom, setPrenom] = useState('');
  const [email, setEmail] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [role, setRole] = useState(ROLES[0].valeur);
  const [erreurs, setErreurs] = useState({});
  const [erreurGenerale, setErreurGenerale] = useState(null);
  const [enCours, setEnCours] = useState(false);

  async function gererEnvoi(e) {
    e.preventDefault();
    setErreurs({});
    setErreurGenerale(null);
    setEnCours(true);
    try {
      await creerUtilisateur({ nom, prenom, email, mot_de_passe: motDePasse, role });
      onTermine();
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
    <Modale titre="Nouvel utilisateur" onFermer={onFermer}>
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreurGenerale ? <Alerte type="erreur">{erreurGenerale}</Alerte> : null}
        <div className="formulaire__grille">
          <Champ label="Prénom" required value={prenom} onChange={(e) => setPrenom(e.target.value)} erreur={erreurs.prenom} />
          <Champ label="Nom" required value={nom} onChange={(e) => setNom(e.target.value)} erreur={erreurs.nom} />
        </div>
        <Champ label="E-mail" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} erreur={erreurs.email} />
        <Champ
          label="Mot de passe temporaire"
          type="password"
          required
          minLength={12}
          value={motDePasse}
          onChange={(e) => setMotDePasse(e.target.value)}
          erreur={erreurs.mot_de_passe}
          aide="12 caractères minimum. À communiquer à l'utilisateur pour sa première connexion."
        />
        <Select label="Rôle" value={role} onChange={(e) => setRole(e.target.value)} erreur={erreurs.role}>
          {ROLES.map((r) => <option key={r.valeur} value={r.valeur}>{r.libelle}</option>)}
        </Select>
        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onFermer}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours}>Créer le compte</Bouton>
        </div>
      </form>
    </Modale>
  );
}
