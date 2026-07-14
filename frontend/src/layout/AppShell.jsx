import { useState } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { navigationPourRole } from './navigation';
import { Bouton } from '../components/ui/Bouton';
import { IconeNav } from './icones';
import './AppShell.css';

const LIBELLES_ROLES = {
  agent_scolarite: 'Agent de scolarité',
  enseignant: 'Enseignant',
  chef_departement: 'Chef de département',
  responsable_academique: 'Responsable académique',
  etudiant: 'Étudiant',
  admin: 'Administrateur',
  directeur: 'Directeur',
};

export function AppShell() {
  const { utilisateur, seDeconnecter } = useAuth();
  const [menuOuvert, setMenuOuvert] = useState(false);
  const location = useLocation();
  const items = navigationPourRole(utilisateur.role);
  const itemCourant = items.find((item) => item.to === location.pathname);

  return (
    <div className="app-shell">
      <a href="#contenu-principal" className="skip-link">Aller au contenu principal</a>

      <header className="app-shell__topbar">
        <button
          type="button"
          className="app-shell__bouton-menu"
          aria-label={menuOuvert ? 'Fermer le menu' : 'Ouvrir le menu'}
          aria-expanded={menuOuvert}
          onClick={() => setMenuOuvert((v) => !v)}
        >
          <span className="app-shell__icone-menu" aria-hidden="true" data-ouvert={menuOuvert} />
        </button>

        <div className="app-shell__marque">
          <span className="app-shell__monogramme">S</span>
          <span className="app-shell__nom">SIARN</span>
        </div>

        <h1 className="app-shell__titre-section">{itemCourant?.label ?? ''}</h1>

        <div className="app-shell__utilisateur">
          <div className="app-shell__identite">
            <span className="app-shell__nom-utilisateur">{utilisateur.prenom} {utilisateur.nom}</span>
            <span className="app-shell__role">{LIBELLES_ROLES[utilisateur.role] ?? utilisateur.role}</span>
          </div>
          <Bouton variante="fantome" onClick={seDeconnecter}>Déconnexion</Bouton>
        </div>
      </header>

      <div className="app-shell__corps">
        {menuOuvert ? (
          <button
            type="button"
            className="app-shell__voile"
            aria-label="Fermer le menu"
            onClick={() => setMenuOuvert(false)}
          />
        ) : null}

        <nav
          className="app-shell__nav"
          data-ouvert={menuOuvert}
          aria-label="Navigation principale"
        >
          <ul>
            {items.map((item) => (
              <li key={item.to}>
                <NavLink
                  to={item.to}
                  end={item.to === '/'}
                  className={({ isActive }) => `app-shell__lien ${isActive ? 'app-shell__lien--actif' : ''}`}
                  onClick={() => setMenuOuvert(false)}
                >
                  <span className="app-shell__lien-icone"><IconeNav chemin={item.to} /></span>
                  <span>{item.label}</span>
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>

        <main id="contenu-principal" className="app-shell__contenu">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
