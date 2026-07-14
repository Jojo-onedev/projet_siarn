import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';
import { obtenirPv } from '../../api/pv';
import { Badge } from '../../components/ui/Badge';
import { Alerte } from '../../components/ui/Alerte';
import { libelleStatut, teinteStatut } from './statuts';
import PvVerificationPanel from './PvVerificationPanel';
import PvNotesPanel from './PvNotesPanel';
import PvValidationPanel from './PvValidationPanel';
import PvPublicationPanel from './PvPublicationPanel';
import './pv.css';

export default function PvDetailPage() {
  const { id } = useParams();
  const { utilisateur } = useAuth();
  const [pv, setPv] = useState(null);
  const [erreur, setErreur] = useState(null);
  const [chargement, setChargement] = useState(true);

  useEffect(() => {
    obtenirPv(id)
      .then(setPv)
      .catch(() => setErreur('Impossible de charger ce procès-verbal.'))
      .finally(() => setChargement(false));
  }, [id]);

  if (chargement) return <p>Chargement…</p>;
  if (erreur) return <Alerte type="erreur">{erreur}</Alerte>;
  if (!pv) return null;

  const peutVerifier = utilisateur.role === 'agent_scolarite' && ['en_verification', 'complement_requis'].includes(pv.statut);

  return (
    <div>
      <Link to="/pv" className="pv-detail__retour">← Retour aux procès-verbaux</Link>

      <div className="page-entete pv-detail__entete">
        <div>
          <p className="page-entete__eyebrow">{pv.code_matiere} · {pv.filiere?.nom}</p>
          <h1>{pv.nom_fichier}</h1>
          <p>{pv.semestre} · {pv.annee_academique} · déposé par {pv.depose_par ? `${pv.depose_par.prenom} ${pv.depose_par.nom}` : '—'}</p>
        </div>
        <Badge teinte={teinteStatut(pv.statut)}>{libelleStatut(pv.statut)}</Badge>
      </div>

      {pv.statut === 'erreur_extraction' ? (
        <Alerte type="erreur" titre="Erreur d'extraction">
          L'extraction OCR n'a pas pu être exploitée (confiance trop faible ou modèle indisponible). Un nouvel import est nécessaire.
        </Alerte>
      ) : null}

      <section className="pv-detail__section">
        <h2>Vérification des champs</h2>
        {peutVerifier ? (
          <PvVerificationPanel pv={pv} onMisAJour={setPv} />
        ) : pv.champs_extraits?.length ? (
          <div className="pv-champs">
            {pv.champs_extraits.map((champ) => (
              <div key={champ.champ} className="pv-champs__carte">
                <div className="pv-champs__entete">
                  <span className="pv-champs__nom">{champ.champ.replace(/_/g, ' ')}</span>
                  {champ.verification_requise ? <Badge teinte="warning">Vérification requise</Badge> : <Badge teinte="success">Fiable</Badge>}
                </div>
                <p className="pv-champs__valeur">{champ.valeur_validee ?? champ.valeur_ocr ?? '—'}</p>
                <p className="pv-champs__meta">
                  Confiance OCR : {Math.round((champ.score_confiance ?? 0) * 100)}%
                  {champ.valeur_validee ? ' · corrigé manuellement' : ''}
                </p>
              </div>
            ))}
          </div>
        ) : <p>Aucune extraction disponible pour l'instant.</p>}
      </section>

      <section className="pv-detail__section">
        <h2>Notes</h2>
        <PvNotesPanel pv={pv} />
      </section>

      {pv.statut === 'en_validation' && ['chef_departement', 'responsable_academique'].includes(utilisateur.role) ? (
        <section className="pv-detail__section">
          <h2>Validation hiérarchique</h2>
          <PvValidationPanel pv={pv} onMisAJour={setPv} />
        </section>
      ) : null}

      {pv.statut === 'integre' && ['agent_scolarite', 'responsable_academique', 'admin'].includes(utilisateur.role) ? (
        <section className="pv-detail__section">
          <h2>Publication</h2>
          <PvPublicationPanel pv={pv} onMisAJour={setPv} />
        </section>
      ) : null}

      <section className="pv-detail__section">
        <h2>Historique</h2>
        {pv.historique?.length ? (
          <ol className="pv-timeline">
            {pv.historique.map((h, i) => (
              <li key={i} className="pv-timeline__item">
                <span className="pv-timeline__point" />
                <div>
                  <p className="pv-timeline__statut">
                    {h.ancien_statut ? `${libelleStatut(h.ancien_statut)} → ` : ''}{libelleStatut(h.nouveau_statut)}
                  </p>
                  <p className="pv-timeline__date">{new Date(h.date_heure).toLocaleString('fr-FR')}</p>
                  {h.motif ? <p className="pv-timeline__motif">{h.motif}</p> : null}
                </div>
              </li>
            ))}
          </ol>
        ) : <p>Aucun historique disponible.</p>}
      </section>
    </div>
  );
}
