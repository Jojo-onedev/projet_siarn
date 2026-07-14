import './AuthLayout.css';

export function AuthLayout({ eyebrow, titre, sousTitre, children }) {
  return (
    <div className="auth-mise-en-page">
      <aside className="auth-mise-en-page__panneau" aria-hidden="true">
        <div className="auth-mise-en-page__marque">
          <span className="auth-mise-en-page__monogramme">S</span>
          <span className="auth-mise-en-page__nom">SIARN</span>
        </div>
        <p className="auth-mise-en-page__accroche">
          Le report de notes, <em>de la salle d'examen au relevé officiel</em>,
          sans ressaisie ni perte de traçabilité.
        </p>
        <div className="auth-mise-en-page__trait" />
      </aside>

      <main className="auth-mise-en-page__contenu">
        <div className="auth-mise-en-page__carte">
          <div className="auth-mise-en-page__marque auth-mise-en-page__marque--mobile">
            <span className="auth-mise-en-page__monogramme">S</span>
            <span className="auth-mise-en-page__nom">SIARN</span>
          </div>
          {eyebrow ? <p className="auth-mise-en-page__eyebrow">{eyebrow}</p> : null}
          <h1 className="auth-mise-en-page__titre">{titre}</h1>
          {sousTitre ? <p className="auth-mise-en-page__sous-titre">{sousTitre}</p> : null}
          {children}
        </div>
      </main>
    </div>
  );
}
