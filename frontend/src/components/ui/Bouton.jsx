import './ui.css';

export function Bouton({ variante = 'primaire', pleineLargeur, chargement, children, ...props }) {
  const classes = ['bouton', `bouton--${variante}`, pleineLargeur ? 'bouton--pleine-largeur' : ''].filter(Boolean).join(' ');
  return (
    <button className={classes} disabled={chargement || props.disabled} {...props}>
      {chargement ? <span className="bouton__spinner" aria-hidden="true" /> : null}
      <span>{children}</span>
    </button>
  );
}
