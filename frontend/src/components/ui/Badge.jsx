import './ui.css';

export function Badge({ teinte = 'accent', children }) {
  return <span className={`badge badge--${teinte}`}>{children}</span>;
}
