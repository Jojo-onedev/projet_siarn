import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from './AuthContext';

// Garde d'affichage uniquement — le RBAC reel est deja verifie cote serveur
// a chaque requete (docs/RECETTE.md critere #5). Cacher un lien ou rediriger
// ici sert l'ergonomie, ce n'est jamais la frontiere de securite.
export function ProtectedRoute({ roles, children }) {
  const { estConnecte, utilisateur, pret } = useAuth();
  const location = useLocation();

  if (!pret) return null;

  if (!estConnecte) {
    return <Navigate to="/connexion" replace state={{ from: location }} />;
  }

  if (roles && !roles.includes(utilisateur.role)) {
    return (
      <div className="etat-page" role="alert">
        <h1>Acces non disponible</h1>
        <p>Votre role ({utilisateur.role}) ne donne pas acces a cet ecran.</p>
      </div>
    );
  }

  return children;
}
