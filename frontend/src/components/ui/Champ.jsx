import { useId } from 'react';
import './ui.css';

export function Champ({ label, erreur, aide, type = 'text', ...props }) {
  const id = useId();
  const idErreur = `${id}-erreur`;
  const idAide = `${id}-aide`;

  return (
    <div className="champ">
      <label htmlFor={id} className="champ__label">{label}</label>
      <input
        id={id}
        type={type}
        className={`champ__input ${erreur ? 'champ__input--erreur' : ''}`}
        aria-invalid={erreur ? 'true' : undefined}
        aria-describedby={[erreur ? idErreur : null, aide ? idAide : null].filter(Boolean).join(' ') || undefined}
        {...props}
      />
      {aide ? <p id={idAide} className="champ__aide">{aide}</p> : null}
      {erreur ? <p id={idErreur} className="champ__erreur" role="alert">{erreur}</p> : null}
    </div>
  );
}
