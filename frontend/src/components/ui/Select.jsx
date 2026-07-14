import { useId } from 'react';
import './ui.css';

export function Select({ label, erreur, aide, enfants, children, ...props }) {
  const id = useId();

  return (
    <div className="champ">
      <label htmlFor={id} className="champ__label">{label}</label>
      <select id={id} className={`champ__input champ__select ${erreur ? 'champ__input--erreur' : ''}`} {...props}>
        {children ?? enfants}
      </select>
      {aide ? <p className="champ__aide">{aide}</p> : null}
      {erreur ? <p className="champ__erreur" role="alert">{erreur}</p> : null}
    </div>
  );
}
