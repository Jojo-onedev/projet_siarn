import { useEffect, useState } from 'react';
import { obtenirDocumentCorpus, annoterDocumentCorpus } from '../../api/corpus';
import { Modale } from '../../components/ui/Modale';
import { Champ } from '../../components/ui/Champ';
import { Select } from '../../components/ui/Select';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { ErreurApi } from '../../api/client';

// Pas d'apercu image ici (contrairement a la verification PV, §7.5) :
// le corpus n'a pas de route de service d'image dediee - saisie des
// coordonnees en valeurs numeriques, limite connue a lever si l'usage
// reel du corpus le justifie.
export default function DocumentCorpusDetail({ documentId, onFermer }) {
  const [doc, setDoc] = useState(null);
  const [erreur, setErreur] = useState(null);

  useEffect(() => {
    obtenirDocumentCorpus(documentId).then(setDoc).catch(() => setErreur('Impossible de charger ce document.'));
  }, [documentId]);

  function gererNouvelleAnnotation(annotation) {
    setDoc((d) => ({ ...d, annotations: [...d.annotations, annotation] }));
  }

  return (
    <Modale titre={doc?.nom_fichier ?? 'Document du corpus'} onFermer={onFermer} largeur="700px">
      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      {!doc ? <p>Chargement…</p> : (
        <div className="formulaire">
          <p>Gabarit : {doc.type_gabarit} · Jeu : {doc.jeu ?? 'non assigné'}</p>

          <h3>Annotations existantes</h3>
          {doc.annotations.length ? (
            <ul className="pv-notes__liste">
              {doc.annotations.map((a) => (
                <li key={a.id} className="pv-notes__ligne">
                  <span>{a.champ} (ordre {a.ordre_annotation})</span>
                  <span>« {a.valeur_verite_terrain} »</span>
                  <Badge teinte={a.statut_verification === 'valide' ? 'success' : 'neutre'}>{a.statut_verification}</Badge>
                </li>
              ))}
            </ul>
          ) : <p>Aucune annotation pour l'instant.</p>}

          <h3>Ajouter une annotation</h3>
          <FormulaireAnnotation documentId={documentId} onCree={gererNouvelleAnnotation} />
        </div>
      )}
    </Modale>
  );
}

function FormulaireAnnotation({ documentId, onCree }) {
  const [champ, setChamp] = useState('');
  const [valeur, setValeur] = useState('');
  const [ordre, setOrdre] = useState('1');
  const [x, setX] = useState('0');
  const [y, setY] = useState('0');
  const [largeur, setLargeur] = useState('100');
  const [hauteur, setHauteur] = useState('100');
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState(null);

  async function gererEnvoi(e) {
    e.preventDefault();
    setErreur(null);
    setEnCours(true);
    try {
      const annotation = await annoterDocumentCorpus(documentId, {
        champ,
        valeur_verite_terrain: valeur,
        coordonnees_zone: { x: Number(x), y: Number(y), largeur: Number(largeur), hauteur: Number(hauteur) },
        ordre_annotation: Number(ordre),
      });
      onCree(annotation);
      setChamp('');
      setValeur('');
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Enregistrement impossible.');
    } finally {
      setEnCours(false);
    }
  }

  return (
    <form className="formulaire" onSubmit={gererEnvoi} noValidate>
      {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
      <div className="formulaire__grille">
        <Champ label="Champ" required value={champ} onChange={(e) => setChamp(e.target.value)} placeholder="ex. en_tete" />
        <Select label="Ordre d'annotation" value={ordre} onChange={(e) => setOrdre(e.target.value)}>
          <option value="1">1 (première passe)</option>
          <option value="2">2 (double annotation)</option>
        </Select>
      </div>
      <Champ label="Valeur de référence (vérité terrain)" required value={valeur} onChange={(e) => setValeur(e.target.value)} />
      <div className="formulaire__grille">
        <Champ label="X" type="number" value={x} onChange={(e) => setX(e.target.value)} />
        <Champ label="Y" type="number" value={y} onChange={(e) => setY(e.target.value)} />
        <Champ label="Largeur" type="number" value={largeur} onChange={(e) => setLargeur(e.target.value)} />
        <Champ label="Hauteur" type="number" value={hauteur} onChange={(e) => setHauteur(e.target.value)} />
      </div>
      <div className="formulaire__actions">
        <Bouton type="submit" chargement={enCours} disabled={!champ || !valeur}>Ajouter l'annotation</Bouton>
      </div>
    </form>
  );
}
