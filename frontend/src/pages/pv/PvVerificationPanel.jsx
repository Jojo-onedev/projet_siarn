import { useEffect, useRef, useState } from 'react';
import { verifierPv, obtenirImagePv } from '../../api/pv';
import { Bouton } from '../../components/ui/Bouton';
import { Alerte } from '../../components/ui/Alerte';
import { Badge } from '../../components/ui/Badge';
import { ErreurApi } from '../../api/client';
import './pv.css';

// Ecran de verification humaine (§7.5, non negociable : aucune note n'est
// jamais publiee sans passage par cet ecran). L'image affichee est la
// version PRETRAITEE : les coordonnees de zones_segmentees sont calculees
// par ocr-service sur cette image, pas sur l'original (cf. pretraitement.py).
export default function PvVerificationPanel({ pv, onMisAJour }) {
  const [valeurs, setValeurs] = useState(() =>
    Object.fromEntries((pv.champs_extraits ?? []).map((c) => [c.champ, c.valeur_validee ?? c.valeur_ocr ?? ''])));
  const [zoneActive, setZoneActive] = useState(null);
  const [imageUrl, setImageUrl] = useState(null);
  const [imageIndisponible, setImageIndisponible] = useState(false);
  const [tailleNaturelle, setTailleNaturelle] = useState(null);
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState(null);
  const [succes, setSucces] = useState(null);
  const conteneurImageRef = useRef(null);
  const champsRefs = useRef({});

  useEffect(() => {
    let urlCourante = null;
    obtenirImagePv(pv.id, 'pretraitee')
      .then((blob) => {
        urlCourante = URL.createObjectURL(blob);
        setImageUrl(urlCourante);
      })
      .catch(() => setImageIndisponible(true));
    return () => { if (urlCourante) URL.revokeObjectURL(urlCourante); };
  }, [pv.id]);

  function gererChangement(champ, valeur) {
    setValeurs((v) => ({ ...v, [champ]: valeur }));
  }

  function survolerZone(nomZone) {
    setZoneActive(nomZone);
    champsRefs.current[nomZone]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  async function gererEnregistrement(e) {
    e.preventDefault();
    setErreur(null);
    setSucces(null);
    setEnCours(true);
    try {
      const corrections = Object.entries(valeurs).map(([champ, valeur_validee]) => ({ champ, valeur_validee }));
      const pvMisAJour = await verifierPv(pv.id, corrections);
      setSucces(pvMisAJour.statut === 'en_validation'
        ? 'Toutes les valeurs sont confirmées : le dossier est transmis à la validation hiérarchique.'
        : 'Corrections enregistrées.');
      onMisAJour(pvMisAJour);
    } catch (err) {
      setErreur(err instanceof ErreurApi ? err.message : 'Enregistrement impossible.');
    } finally {
      setEnCours(false);
    }
  }

  const echelle = tailleNaturelle && conteneurImageRef.current
    ? conteneurImageRef.current.clientWidth / tailleNaturelle.largeur
    : 1;

  return (
    <div className="pv-verif">
      <div className="pv-verif__image" ref={conteneurImageRef}>
        {imageIndisponible ? (
          <p className="pv-verif__image-absente">Image indisponible pour ce PV.</p>
        ) : imageUrl ? (
          <>
            <img
              src={imageUrl}
              alt="Version prétraitée du procès-verbal"
              onLoad={(e) => setTailleNaturelle({ largeur: e.target.naturalWidth, hauteur: e.target.naturalHeight })}
            />
            {tailleNaturelle && (pv.zones_segmentees ?? []).map((zone) => (
              <button
                type="button"
                key={zone.nom}
                className={`pv-verif__zone ${zoneActive === zone.nom ? 'pv-verif__zone--active' : ''}`}
                style={{
                  left: zone.x * echelle,
                  top: zone.y * echelle,
                  width: zone.largeur * echelle,
                  height: zone.hauteur * echelle,
                }}
                onMouseEnter={() => setZoneActive(zone.nom)}
                onMouseLeave={() => setZoneActive(null)}
                onClick={() => survolerZone(zone.nom)}
                aria-label={`Aller au champ ${zone.nom}`}
              />
            ))}
          </>
        ) : <p>Chargement de l'image…</p>}
      </div>

      <form className="pv-verif__formulaire" onSubmit={gererEnregistrement}>
        <h2>Vérification des champs extraits</h2>
        <p className="pv-verif__aide">Comparez chaque valeur au document ci-contre et corrigez si nécessaire avant d'enregistrer.</p>

        {erreur ? <Alerte type="erreur">{erreur}</Alerte> : null}
        {succes ? <Alerte type="succes">{succes}</Alerte> : null}

        {(pv.champs_extraits ?? []).map((champ) => (
          <div
            key={champ.champ}
            ref={(el) => { champsRefs.current[champ.champ] = el; }}
            className={`pv-verif__champ ${zoneActive === champ.champ ? 'pv-verif__champ--surligne' : ''}`}
            onMouseEnter={() => setZoneActive(champ.champ)}
            onMouseLeave={() => setZoneActive(null)}
          >
            <div className="pv-verif__champ-entete">
              <span className="champ__label">{champ.champ.replace(/_/g, ' ')}</span>
              {champ.verification_requise
                ? <Badge teinte="warning">{Math.round((champ.score_confiance ?? 0) * 100)}% de confiance</Badge>
                : <Badge teinte="success">{Math.round((champ.score_confiance ?? 0) * 100)}% de confiance</Badge>}
            </div>
            <textarea
              className="champ__input pv-verif__textarea"
              value={valeurs[champ.champ]}
              onChange={(e) => gererChangement(champ.champ, e.target.value)}
              rows={champ.champ === 'tableau_notes' ? 4 : 1}
            />
            {champ.valeur_ocr !== valeurs[champ.champ] ? (
              <p className="pv-verif__ocr-brut">Valeur OCR d'origine : « {champ.valeur_ocr || '(vide)'} »</p>
            ) : null}
          </div>
        ))}

        <div className="formulaire__actions">
          <Bouton type="submit" chargement={enCours}>Enregistrer les corrections</Bouton>
        </div>
      </form>
    </div>
  );
}
