import { useState } from 'react';
import { validerPv } from '../../api/pv';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';

const DECISIONS = [
  { valeur: 'valider', libelle: 'Valider le dossier', variante: 'primaire' },
  { valeur: 'complement_requis', libelle: 'Demander un complément', variante: 'secondaire' },
  { valeur: 'rejeter', libelle: 'Rejeter', variante: 'secondaire' },
];

// §7.6/§9.1 : Chef de departement (sa filiere, deja verifie cote serveur -
// un 403 explicite y revient sinon) ou Responsable academique (3 filieres).
export default function PvValidationPanel({ pv, onMisAJour }) {
  const [decision, setDecision] = useState(null);
  const [motif, setMotif] = useState('');
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function confirmer() {
    setErreur(null);
    setEnCours(true);
    try {
      const pvMisAJour = await validerPv(pv.id, decision, motif || undefined);
      onMisAJour(pvMisAJour);
      setDecision(null);
      setMotif('');
    } catch (err) {
      setErreur(err?.message ?? 'Décision impossible à enregistrer.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <div className="formulaire">
      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      <p>Le dossier a été entièrement vérifié par l'agent de scolarité. Choisissez une décision :</p>
      <div className="section-entete__actions">
        {DECISIONS.map((d) => (
          <Bouton key={d.valeur} variante={d.variante} onClick={() => setDecision(d.valeur)}>{d.libelle}</Bouton>
        ))}
      </div>

      {decision ? (
        <div className="pv-validation__confirmation">
          {decision !== 'valider' ? (
            <textarea
              className="champ__input"
              placeholder="Motif (obligatoire)"
              value={motif}
              onChange={(e) => setMotif(e.target.value)}
              rows={3}
              required
            />
          ) : null}
          <div className="formulaire__actions">
            <Bouton variante="secondaire" onClick={() => setDecision(null)}>Annuler</Bouton>
            <Bouton onClick={confirmer} chargement={enCours} disabled={decision !== 'valider' && !motif}>
              Confirmer
            </Bouton>
          </div>
        </div>
      ) : null}
    </div>
  );
}
