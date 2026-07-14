import { useEffect, useState } from 'react';
import { useAuth } from '../../auth/AuthContext';
import { listerFilieres, listerEtudiants, creerEtudiant, modifierEtudiant, importerEtudiants } from '../../api/referentiels';
import { ErreurApi } from '../../api/client';
import { Tableau } from '../../components/ui/Tableau';
import { Modale } from '../../components/ui/Modale';
import { Champ } from '../../components/ui/Champ';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { NIVEAUX, anneesAcademiques } from './constantes';
import { genererMatricule, genererMotDePasse, suggererEmail } from './generateurs';
import './referentiels.css';

export default function EtudiantsSection() {
  const { utilisateur } = useAuth();
  const peutEcrire = ['agent_scolarite', 'admin'].includes(utilisateur.role);

  const [donnees, setDonnees] = useState({ donnees: [], total: 0, page: 1, dernieres_pages: 1 });
  const [filieres, setFilieres] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [q, setQ] = useState('');
  const [filiereId, setFiliereId] = useState('');
  const [niveau, setNiveau] = useState('');
  const [page, setPage] = useState(1);
  const [modaleOuverte, setModaleOuverte] = useState(false);
  const [etudiantEnEdition, setEtudiantEnEdition] = useState(null);
  const [modaleImportOuverte, setModaleImportOuverte] = useState(false);

  useEffect(() => { listerFilieres().then(setFilieres).catch(() => {}); }, []);

  useEffect(() => {
    charger();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q, filiereId, niveau, page]);

  function charger() {
    setChargement(true);
    listerEtudiants({ q, filiere_id: filiereId, niveau, page })
      .then(setDonnees)
      .catch(() => setErreur('Impossible de charger les étudiants.'))
      .finally(() => setChargement(false));
  }

  // Ne ferme plus la modale automatiquement : quand un compte de connexion
  // est cree, FormulaireEtudiant affiche un recapitulatif (email/mot de
  // passe) avant de fermer, pour que l'agent ait le temps de les recuperer.
  async function gererEnregistrement(payload) {
    const resultat = etudiantEnEdition
      ? await modifierEtudiant(etudiantEnEdition.id, payload)
      : await creerEtudiant(payload);
    charger();
    return resultat;
  }

  function fermerModale() {
    setModaleOuverte(false);
  }

  const colonnes = [
    { cle: 'matricule', entete: 'Matricule' },
    { cle: 'nom', entete: 'Nom' },
    { cle: 'prenom', entete: 'Prénom' },
    { cle: 'filiere', entete: 'Filière', rendu: (e) => e.filiere?.nom ?? '—' },
    { cle: 'niveau', entete: 'Niveau' },
    { cle: 'annee_academique', entete: 'Année' },
    { cle: 'compte_lie', entete: 'Compte portail', rendu: (e) => <Badge teinte={e.compte_lie ? 'success' : 'neutre'}>{e.compte_lie ? 'Lié' : 'Aucun'}</Badge> },
    { cle: 'actif', entete: 'Statut', rendu: (e) => <Badge teinte={e.actif ? 'success' : 'neutre'}>{e.actif ? 'Actif' : 'Inactif'}</Badge> },
  ];

  return (
    <div>
      <div className="section-entete">
        <div className="filtres">
          <Champ label="Rechercher" placeholder="Nom, prénom, matricule…" value={q} onChange={(e) => { setPage(1); setQ(e.target.value); }} />
          <Select label="Filière" value={filiereId} onChange={(e) => { setPage(1); setFiliereId(e.target.value); }}>
            <option value="">Toutes</option>
            {filieres.map((f) => <option key={f.id} value={f.id}>{f.nom}</option>)}
          </Select>
          <Select label="Niveau" value={niveau} onChange={(e) => { setPage(1); setNiveau(e.target.value); }}>
            <option value="">Tous</option>
            {NIVEAUX.map((n) => <option key={n} value={n}>{n}</option>)}
          </Select>
        </div>
        {peutEcrire ? (
          <div className="section-entete__actions">
            <Bouton variante="secondaire" onClick={() => setModaleImportOuverte(true)}>Importer un CSV</Bouton>
            <Bouton onClick={() => { setEtudiantEnEdition(null); setModaleOuverte(true); }}>Nouvel étudiant</Bouton>
          </div>
        ) : null}
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <>
          <Tableau
            colonnes={colonnes}
            lignes={donnees.donnees}
            cleLigne="id"
            surLigneClic={peutEcrire ? (e) => { setEtudiantEnEdition(e); setModaleOuverte(true); } : undefined}
            vide="Aucun étudiant ne correspond a ces criteres."
          />
          {donnees.dernieres_pages > 1 ? (
            <div className="pagination">
              <Bouton variante="secondaire" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Précédent</Bouton>
              <span>Page {donnees.page} / {donnees.dernieres_pages} ({donnees.total} étudiants)</span>
              <Bouton variante="secondaire" disabled={page >= donnees.dernieres_pages} onClick={() => setPage((p) => p + 1)}>Suivant</Bouton>
            </div>
          ) : null}
        </>
      )}

      {modaleOuverte ? (
        <FormulaireEtudiant
          etudiant={etudiantEnEdition}
          filieres={filieres}
          onAnnuler={fermerModale}
          onEnregistrer={gererEnregistrement}
          onTermine={fermerModale}
        />
      ) : null}

      {modaleImportOuverte ? (
        <ModaleImport onFermer={() => setModaleImportOuverte(false)} onTermine={() => { setModaleImportOuverte(false); charger(); }} />
      ) : null}
    </div>
  );
}

function FormulaireEtudiant({ etudiant, filieres, onAnnuler, onEnregistrer, onTermine }) {
  const [matricule, setMatricule] = useState(etudiant?.matricule ?? genererMatricule());
  const [nom, setNom] = useState(etudiant?.nom ?? '');
  const [prenom, setPrenom] = useState(etudiant?.prenom ?? '');
  const [filiereId, setFiliereId] = useState(etudiant?.filiere?.id ?? '');
  const [niveau, setNiveau] = useState(etudiant?.niveau ?? NIVEAUX[0]);
  const [anneeAcademique, setAnneeAcademique] = useState(etudiant?.annee_academique ?? anneesAcademiques()[1]);

  // Compte de connexion (creation uniquement, optionnel) - cf. remarque
  // utilisateur : jusqu'ici rien ne permettait de lier un compte au profil,
  // un etudiant reel ne pouvait donc jamais se connecter au portail (§7.6).
  const [creerCompte, setCreerCompte] = useState(false);
  const [email, setEmail] = useState('');
  const [emailModifieManuellement, setEmailModifieManuellement] = useState(false);
  const [motDePasse, setMotDePasse] = useState('');
  const [motDePasseVisible, setMotDePasseVisible] = useState(false);
  const [motDePasseCopie, setMotDePasseCopie] = useState(false);

  const [erreurs, setErreurs] = useState({});
  const [erreurGenerale, setErreurGenerale] = useState(null);
  const [enCours, setEnCours] = useState(false);
  const [recapCompte, setRecapCompte] = useState(null);

  useEffect(() => {
    if (!etudiant && creerCompte && !emailModifieManuellement) {
      setEmail(suggererEmail(prenom, nom));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [prenom, nom, creerCompte]);

  function activerCreationCompte(active) {
    setCreerCompte(active);
    if (active && !motDePasse) setMotDePasse(genererMotDePasse());
    if (active && !email) setEmail(suggererEmail(prenom, nom));
  }

  async function copierMotDePasse() {
    try {
      await navigator.clipboard.writeText(motDePasse);
      setMotDePasseCopie(true);
      setTimeout(() => setMotDePasseCopie(false), 2000);
    } catch {
      // Presse-papiers indisponible : le mot de passe reste lisible/selectionnable a l'ecran.
    }
  }

  async function gererEnvoi(e) {
    e.preventDefault();
    setErreurs({});
    setErreurGenerale(null);
    setEnCours(true);
    try {
      const payload = { matricule, nom, prenom, filiere_id: filiereId, niveau, annee_academique: anneeAcademique };
      if (!etudiant && creerCompte) {
        payload.email = email;
        payload.mot_de_passe = motDePasse;
      }
      await onEnregistrer(payload);

      if (!etudiant && creerCompte) {
        setRecapCompte({ email, motDePasse });
      } else {
        onTermine();
      }
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

  if (recapCompte) {
    return (
      <Modale titre="Étudiant créé" onFermer={onTermine}>
        <div className="formulaire">
          <Alerte type="succes" titre="Profil et compte de connexion créés">
            Communiquez ces identifiants à l'étudiant — le mot de passe ne sera plus jamais affiché après fermeture de cette fenêtre.
          </Alerte>
          <div className="auth-secret">
            <span className="champ__label">E-mail</span>
            <code className="auth-secret__valeur">{recapCompte.email}</code>
          </div>
          <div className="auth-secret">
            <span className="champ__label">Mot de passe temporaire</span>
            <code className="auth-secret__valeur">{recapCompte.motDePasse}</code>
            <Bouton type="button" variante="secondaire" onClick={copierMotDePasse}>
              {motDePasseCopie ? 'Copié !' : 'Copier le mot de passe'}
            </Bouton>
          </div>
          <div className="formulaire__actions">
            <Bouton onClick={onTermine}>Terminé</Bouton>
          </div>
        </div>
      </Modale>
    );
  }

  return (
    <Modale titre={etudiant ? 'Modifier l\'étudiant' : 'Nouvel étudiant'} onFermer={onAnnuler} largeur="640px">
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreurGenerale ? <Alerte type="erreur">{erreurGenerale}</Alerte> : null}

        <div className="champ-avec-bouton">
          <Champ label="Matricule" required value={matricule} onChange={(e) => setMatricule(e.target.value)} erreur={erreurs.matricule} />
          <Bouton type="button" variante="secondaire" onClick={() => setMatricule(genererMatricule())}>Générer</Bouton>
        </div>

        <div className="formulaire__grille">
          <Champ label="Nom" required value={nom} onChange={(e) => setNom(e.target.value)} erreur={erreurs.nom} />
          <Champ label="Prénom" required value={prenom} onChange={(e) => setPrenom(e.target.value)} erreur={erreurs.prenom} />
        </div>
        <Select label="Filière" required value={filiereId} onChange={(e) => setFiliereId(e.target.value)} erreur={erreurs.filiere_id}>
          <option value="" disabled>Sélectionner une filière</option>
          {filieres.map((f) => <option key={f.id} value={f.id}>{f.nom}</option>)}
        </Select>
        <div className="formulaire__grille">
          <Select label="Niveau" value={niveau} onChange={(e) => setNiveau(e.target.value)} erreur={erreurs.niveau}>
            {NIVEAUX.map((n) => <option key={n} value={n}>{n}</option>)}
          </Select>
          <Select label="Année académique" value={anneeAcademique} onChange={(e) => setAnneeAcademique(e.target.value)} erreur={erreurs.annee_academique}>
            {anneesAcademiques().map((a) => <option key={a} value={a}>{a}</option>)}
          </Select>
        </div>

        {!etudiant ? (
          <div className="bloc-compte">
            <label className="bloc-compte__case">
              <input type="checkbox" checked={creerCompte} onChange={(e) => activerCreationCompte(e.target.checked)} />
              <span>Créer aussi un accès au portail étudiant pour cette personne</span>
            </label>

            {creerCompte ? (
              <>
                <div className="champ-avec-bouton">
                  <Champ
                    label="E-mail de connexion"
                    type="email"
                    required
                    value={email}
                    onChange={(e) => { setEmailModifieManuellement(true); setEmail(e.target.value); }}
                    erreur={erreurs.email}
                    aide="Pré-rempli à partir du nom/prénom, modifiable."
                  />
                </div>
                <div className="champ-avec-bouton">
                  <Champ
                    label="Mot de passe temporaire"
                    type={motDePasseVisible ? 'text' : 'password'}
                    required
                    minLength={12}
                    value={motDePasse}
                    onChange={(e) => setMotDePasse(e.target.value)}
                    erreur={erreurs.mot_de_passe}
                  />
                  <Bouton type="button" variante="secondaire" onClick={() => setMotDePasseVisible((v) => !v)}>
                    {motDePasseVisible ? 'Masquer' : 'Afficher'}
                  </Bouton>
                  <Bouton type="button" variante="secondaire" onClick={() => setMotDePasse(genererMotDePasse())}>Générer</Bouton>
                </div>
              </>
            ) : null}
          </div>
        ) : null}

        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onAnnuler}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours}>Enregistrer</Bouton>
        </div>
      </form>
    </Modale>
  );
}

function ModaleImport({ onFermer, onTermine }) {
  const [fichier, setFichier] = useState(null);
  const [enCours, setEnCours] = useState(false);
  const [resultat, setResultat] = useState(null);
  const [erreur, setErreur] = useState(null);

  async function gererEnvoi(e) {
    e.preventDefault();
    if (!fichier) return;
    setEnCours(true);
    setErreur(null);
    try {
      const r = await importerEtudiants(fichier);
      setResultat(r);
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Import impossible.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <Modale titre="Importer une liste d'étudiants (CSV)" onFermer={onFermer}>
      {resultat ? (
        <div className="formulaire">
          <Alerte type="succes" titre="Import terminé">
            {resultat.crees} créé(s), {resultat.mis_a_jour} mis à jour, {resultat.erreurs.length} erreur(s).
          </Alerte>
          <p>L'import de masse ne crée pas de compte de connexion — utilisez « Nouvel étudiant » au cas par cas si un accès portail est nécessaire.</p>
          {resultat.erreurs.length > 0 ? (
            <ul>
              {resultat.erreurs.map((e, i) => <li key={i}>Ligne {e.ligne} : {e.message}</li>)}
            </ul>
          ) : null}
          <div className="formulaire__actions">
            <Bouton onClick={onTermine}>Fermer</Bouton>
          </div>
        </div>
      ) : (
        <form className="formulaire" onSubmit={gererEnvoi}>
          <p>Colonnes attendues : <code>matricule,nom,prenom,filiere_code,niveau,annee_academique</code></p>
          {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
          <input type="file" accept=".csv,.txt" onChange={(e) => setFichier(e.target.files[0])} required />
          <div className="formulaire__actions">
            <Bouton type="button" variante="secondaire" onClick={onFermer}>Annuler</Bouton>
            <Bouton type="submit" chargement={enCours} disabled={!fichier}>Importer</Bouton>
          </div>
        </form>
      )}
    </Modale>
  );
}
