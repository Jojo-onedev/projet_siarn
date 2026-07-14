import './ui.css';

export function Alerte({ type = 'erreur', titre, children }) {
  return (
    <div className={`alerte alerte--${type}`} role={type === 'erreur' ? 'alert' : 'status'}>
      {titre ? <p className="alerte__titre">{titre}</p> : null}
      {children ? <div className="alerte__corps">{children}</div> : null}
    </div>
  );
}
