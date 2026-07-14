import { Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import { ProtectedRoute } from './auth/ProtectedRoute';
import { AppShell } from './layout/AppShell';
import ReferentielsLayout from './layout/ReferentielsLayout';
import LoginPage from './pages/LoginPage';
import MfaVerificationPage from './pages/MfaVerificationPage';
import MfaEnrollmentPage from './pages/MfaEnrollmentPage';
import DashboardHomePage from './pages/DashboardHomePage';
import ComingSoonPage from './pages/ComingSoonPage';
import FilieresSection from './pages/referentiels/FilieresSection';
import ModulesSection from './pages/referentiels/ModulesSection';
import EtudiantsSection from './pages/referentiels/EtudiantsSection';
import { NAVIGATION } from './layout/navigation';

const ROLES_REFERENTIELS = ['agent_scolarite', 'chef_departement', 'responsable_academique', 'directeur', 'admin'];
const ECRANS_A_VENIR = NAVIGATION.filter((item) => item.to !== '/' && item.to !== '/referentiels');

export default function App() {
  return (
    <AuthProvider>
      <Routes>
        <Route path="/connexion" element={<LoginPage />} />
        <Route path="/mfa/verification" element={<MfaVerificationPage />} />
        <Route
          path="/mfa/activation"
          element={(
            <ProtectedRoute>
              <MfaEnrollmentPage />
            </ProtectedRoute>
          )}
        />

        <Route
          element={(
            <ProtectedRoute>
              <AppShell />
            </ProtectedRoute>
          )}
        >
          <Route path="/" element={<DashboardHomePage />} />

          <Route
            path="/referentiels"
            element={(
              <ProtectedRoute roles={ROLES_REFERENTIELS}>
                <ReferentielsLayout />
              </ProtectedRoute>
            )}
          >
            <Route index element={<Navigate to="filieres" replace />} />
            <Route path="filieres" element={<FilieresSection />} />
            <Route path="modules" element={<ModulesSection />} />
            <Route path="etudiants" element={<EtudiantsSection />} />
          </Route>

          {ECRANS_A_VENIR.map((item) => (
            <Route key={item.to} path={item.to} element={<ComingSoonPage />} />
          ))}
          <Route path="*" element={<ComingSoonPage />} />
        </Route>
      </Routes>
    </AuthProvider>
  );
}
