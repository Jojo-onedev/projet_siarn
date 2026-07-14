import { NavLink, Outlet } from 'react-router-dom';
import './ReferentielsLayout.css';

const ONGLETS = [
  { to: '/referentiels/filieres', label: 'Filières' },
  { to: '/referentiels/modules', label: 'Modules' },
  { to: '/referentiels/etudiants', label: 'Étudiants' },
];

export default function ReferentielsLayout() {
  return (
    <div>
      <div className="page-entete">
        <p className="page-entete__eyebrow">Référentiels</p>
        <h1>Filières, modules et étudiants</h1>
        <p>Base commune utilisée par l'import de PV, les notes et les tableaux de bord.</p>
      </div>

      <nav className="onglets" aria-label="Sections des référentiels">
        {ONGLETS.map((o) => (
          <NavLink key={o.to} to={o.to} className={({ isActive }) => `onglets__lien ${isActive ? 'onglets__lien--actif' : ''}`}>
            {o.label}
          </NavLink>
        ))}
      </nav>

      <Outlet />
    </div>
  );
}
