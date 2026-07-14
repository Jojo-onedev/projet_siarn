import { useEffect, useState } from 'react';
import { listerDocumentsCorpus, importerDocumentCorpus, repartirCorpus } from '../../api/corpus';
import { Tableau } from '../../components/ui/Tableau';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { Modale } from '../../components/ui/Modale';
import { Champ } from '../../components/ui/Champ';
import { ErreurApi } from '../../api/client';
import DocumentCorpusDetail from './DocumentCorpusDetail';
import '../referentiels/referentiels.css';

const TEINTES_JEU = { train: 'info', val: 'warning', test: 'success' };

export default function CorpusPage() {
  const [documents, setDocuments] = useState([]);
  const [jeu, setJeu] = useState('');
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState(null);
  const [modaleImport, setModaleImport] = useState(false);
  const [documentOuvert, setDocumentOuvert] = useState(null);
  const [repartitionEnCours, setRepartitionEnCours] = useState(false);
  const [messageRepartition, setMessageRepartition] = useState(null);

  useEffect(() => { charger(); }, [jeu]);

  function charger() {
    setChargement(true);
    listerDocumentsCorpus({ jeu }).then(setDocuments).catch(() => setErreur('Impossible de charger le corpus.')).finally(() => setChargement(false));
  }

  async function gererRepartition() {
    setRepartitionEnCours(true);
    setMessageRepartition(null);
    try {
      const r = await repartirCorpus();
      setMessageRepartition(r.repartis > 0
        ? `${r.repartis} document(s) répartis : ${r.train} train / ${r.val} val / ${r.test} test.`
        : 'Aucun document sans jeu assigné.');
      charger();
    } finally {
      setRepartitionEnCours(false);
    }
  }

  const colonnes = [
    { cle: 'nom_fichier', entete: 'Fichier' },
    { cle: 'type_gabarit', entete: 'Gabarit' },
    { cle: 'jeu', entete: 'Jeu', rendu: (d) => d.jeu ? <Badge teinte={TEINTES_JEU[d.jeu] ?? 'neutre'}>{d.jeu}</Badge> : <Badge teinte="neutre">non assigné</Badge> },
    { cle: 'nombre_annotations', entete: 'Annotations', rendu: (d) => d.nombre_annotations ?? 0 },
    { cle: 'anonymise', entete: 'Anonymisé', rendu: (d) => d.anonymise ? '✓' : '—' },
  ];

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Corpus OCR</p>
        <h1>Constitution et annotation du corpus d'entraînement</h1>
        <p>Indépendant des procès-verbaux de production (§10 du PRD) — ne sert qu'à entraîner/évaluer le modèle OCR.</p>
      </div>

      <div className="section-entete">
        <div className="filtres">
          <Select label="Jeu" value={jeu} onChange={(e) => setJeu(e.target.value)}>
            <option value="">Tous</option>
            <option value="train">Train</option>
            <option value="val">Val</option>
            <option value="test">Test</option>
          </Select>
        </div>
        <div className="section-entete__actions">
          <Bouton variante="secondaire" onClick={gererRepartition} chargement={repartitionEnCours}>Répartir train/val/test</Bouton>
          <Bouton onClick={() => setModaleImport(true)}>Importer un document</Bouton>
        </div>
      </div>

      {messageRepartition ? <Alerte type="info">{messageRepartition}</Alerte> : null}
      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {chargement ? <p>Chargement…</p> : (
        <Tableau
          colonnes={colonnes}
          lignes={documents}
          cleLigne="id"
          surLigneClic={(d) => setDocumentOuvert(d)}
          vide="Aucun document dans le corpus pour l'instant."
        />
      )}

      {modaleImport ? (
        <FormulaireImportCorpus onFermer={() => setModaleImport(false)} onTermine={() => { setModaleImport(false); charger(); }} />
      ) : null}

      {documentOuvert ? (
        <DocumentCorpusDetail documentId={documentOuvert.id} onFermer={() => { setDocumentOuvert(null); charger(); }} />
      ) : null}
    </div>
  );
}

function FormulaireImportCorpus({ onFermer, onTermine }) {
  const [fichier, setFichier] = useState(null);
  const [typeGabarit, setTypeGabarit] = useState('defaut');
  const [jeu, setJeu] = useState('');
  const [anonymise, setAnonymise] = useState(true);
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function gererEnvoi(e) {
    e.preventDefault();
    if (!fichier) return;
    setErreur(null);
    setEnCours(true);
    try {
      await importerDocumentCorpus({ fichier, type_gabarit: typeGabarit, jeu: jeu || undefined, anonymise });
      onTermine();
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Import impossible.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <Modale titre="Importer un document au corpus" onFermer={onFermer}>
      <form className="formulaire" onSubmit={gererEnvoi} noValidate>
        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
        <div className="champ">
          <label className="champ__label" htmlFor="corpus-fichier">Fichier (JPG/PNG)</label>
          <input id="corpus-fichier" type="file" accept=".jpg,.jpeg,.png" className="champ__input" onChange={(e) => setFichier(e.target.files[0])} required />
        </div>
        <Champ label="Gabarit" value={typeGabarit} onChange={(e) => setTypeGabarit(e.target.value)} />
        <Select label="Jeu (optionnel — sinon assigné via la répartition automatique)" value={jeu} onChange={(e) => setJeu(e.target.value)}>
          <option value="">— Non assigné —</option>
          <option value="train">Train</option>
          <option value="val">Val</option>
          <option value="test">Test</option>
        </Select>
        <label className="pv-notes__ligne" style={{ cursor: 'pointer' }}>
          <input type="checkbox" checked={anonymise} onChange={(e) => setAnonymise(e.target.checked)} />
          <span>Document anonymisé</span>
        </label>
        <div className="formulaire__actions">
          <Bouton type="button" variante="secondaire" onClick={onFermer}>Annuler</Bouton>
          <Bouton type="submit" chargement={enCours} disabled={!fichier}>Importer</Bouton>
        </div>
      </form>
    </Modale>
  );
}

