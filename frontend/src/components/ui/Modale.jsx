import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import './ui.css';

export function Modale({ titre, onFermer, children, largeur = '520px' }) {
  const carteRef = useRef(null);

  useEffect(() => {
    function surEchap(e) {
      if (e.key === 'Escape') onFermer();
    }
    document.addEventListener('keydown', surEchap);
    carteRef.current?.querySelector('input, select, textarea, button')?.focus();
    return () => document.removeEventListener('keydown', surEchap);
  }, [onFermer]);

  return createPortal(
    <div className="modale__voile" role="presentation" onMouseDown={(e) => { if (e.target === e.currentTarget) onFermer(); }}>
      <div className="modale__carte" role="dialog" aria-modal="true" aria-label={titre} style={{ maxWidth: largeur }} ref={carteRef}>
        <div className="modale__entete">
          <h2 className="modale__titre">{titre}</h2>
          <button type="button" className="modale__fermer" aria-label="Fermer" onClick={onFermer}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg>
          </button>
        </div>
        <div className="modale__corps">{children}</div>
      </div>
    </div>,
    document.body,
  );
}
