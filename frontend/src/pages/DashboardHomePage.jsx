import { Link } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { navigationPourRole } from '../layout/navigation';
import { IconeNav } from '../layout/icones';
import './pages.css';

const TEINTES = ['accent', 'success', 'warning', 'info'];

export default function DashboardHomePage() {
  const { utilisateur } = useAuth();
  const raccourcis = navigationPourRole(utilisateur.role).filter((item) => item.to !== '/');

  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Bienvenue</p>
        <h1>Bonjour {utilisateur.prenom}</h1>
        <p>
          Voici les espaces accessibles a votre role. Les ecrans marques d'un
          epic a venir seront livres progressivement.
        </p>
      </div>

      <div className="grille-cartes">
        {raccourcis.map((item, index) => (
          <Link key={item.to} to={item.to} className="carte-lien">
            <span className={`carte-lien__icone carte-lien__icone--${TEINTES[index % TEINTES.length]}`}>
              <IconeNav chemin={item.to} />
            </span>
            {!item.implemente ? <span className="carte-lien__epic">{item.epic}</span> : null}
            <h2>{item.label}</h2>
          </Link>
        ))}
      </div>
    </div>
  );
}
