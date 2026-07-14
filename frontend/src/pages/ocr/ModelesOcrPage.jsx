import { useEffect, useState } from 'react';
import { listerModelesOcr } from '../../api/modelesOcr';
import { Tableau } from '../../components/ui/Tableau';
import { Badge } from '../../components/ui/Badge';
import { Alerte } from '../../components/ui/Alerte';
import '../pages.css';

const TEINTES_STATUT = { actif: 'success', candidat: 'info', archive: 'neutre' };

export default function ModelesOcrPage() {
  const [modeles, setModeles] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);

  useEffect(() => {
    listerModelesOcr().then(setModeles).catch(() => setErreur('Impossible de charger les modèles.')).finally(() => setChargement(false));
  }, []);

  const colonnes = [
    { cle: 'version', entete: 'Version' },
    { cle: 'statut', entete: 'Statut', rendu: (m) => <Badge teinte={TEINTES_STATUT[m.statut] ?? 'neutre'}>{m.statut}</Badge> },
    { cle: 'cer', entete: 'CER', rendu: (m) => `${m.cer}%` },
    { cle: 'wer', entete: 'WER', rendu: (m) => `${m.wer}%` },
    { cle: 'date_entrainement', entete: 'Entraîné le', rendu: (m) => new Date(m.date_entrainement).toLocaleString('fr-FR') },
  ];

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">OCR</p>
        <h1>Modèles OCR entraînés</h1>
        <p>
          L'entraînement lui-même reste un job Docker Compose (<code>docker compose run --rm ocr-training</code>),
          jamais déclenché depuis cette interface (§5 : « Entraîner/évaluer le modèle OCR » → Admin, en développement).
        </p>
      </div>

      <Alerte type="avertissement" titre="CER/WER à interpréter avec prudence">
        Une mesure obtenue sur un corpus synthétique ne reflète pas la performance sur un vrai PV d'établissement
        (voir docs/RECETTE.md). Ne jamais présenter un CER ici comme une garantie de production.
      </Alerte>

      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <Tableau colonnes={colonnes} lignes={modeles} cleLigne="id" vide="Aucun modèle entraîné pour l'instant." />
      )}
    </div>
  );
}
