import { useLocation } from 'react-router-dom';
import { NAVIGATION } from '../layout/navigation';
import './pages.css';

export default function ComingSoonPage() {
  const location = useLocation();
  const item = NAVIGATION.find((n) => n.to === location.pathname);

  return (
    <div className="etat-page">
      <h1>{item?.label ?? 'Écran à venir'}</h1>
      <p>
        Cet écran sera construit dans l'épic {item?.epic ?? 'à venir'} (voir
        PRD_FRONTEND.md, roadmap F1-F8). L'API correspondante existe déjà
        côté backend (voir docs/API.md).
      </p>
    </div>
  );
}
