import { useEffect, useState } from 'react';
import { obtenirMonProfil } from '../../api/portail';
import { Alerte } from '../../components/ui/Alerte';
import '../pages.css';

// GET /mon-profil existait cote API depuis E12 mais n'etait appele nulle
// part cote frontend (trouve en revue manuelle) - petit gain d'usage pour
// l'etudiant : voir son propre profil (matricule, filiere, niveau).
export default function MonProfilPage() {
  const [profil, setProfil] = useState(null);
  const [erreur, setErreur] = useState(null);
  const [chargement, setChargement] = useState(true);

  useEffect(() => {
    obtenirMonProfil().then(setProfil).catch(() => setErreur('Impossible de charger votre profil.')).finally(() => setChargement(false));
  }, []);

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Mon espace</p>
        <h1>Mon profil</h1>
      </div>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : profil ? (
        <div className="carte-stat" style={{ maxWidth: 420 }}>
          <dl style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', margin: 0 }}>
            <div>
              <dt className="tableau__carte-label">Matricule</dt>
              <dd style={{ margin: 0 }}>{profil.matricule}</dd>
            </div>
            <div>
              <dt className="tableau__carte-label">Nom complet</dt>
              <dd style={{ margin: 0 }}>{profil.prenom} {profil.nom}</dd>
            </div>
            <div>
              <dt className="tableau__carte-label">Filière</dt>
              <dd style={{ margin: 0 }}>{profil.filiere?.nom ?? '—'}</dd>
            </div>
            <div>
              <dt className="tableau__carte-label">Niveau</dt>
              <dd style={{ margin: 0 }}>{profil.niveau}</dd>
            </div>
            <div>
              <dt className="tableau__carte-label">Année académique</dt>
              <dd style={{ margin: 0 }}>{profil.annee_academique}</dd>
            </div>
          </dl>
        </div>
      ) : null}
    </div>
  );
}
