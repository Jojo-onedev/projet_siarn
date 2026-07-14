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
import PvListPage from './pages/pv/PvListPage';
import PvDetailPage from './pages/pv/PvDetailPage';
import MonProfilPage from './pages/portail/MonProfilPage';
import MesNotesPage from './pages/portail/MesNotesPage';
import MesReclamationsPage from './pages/portail/MesReclamationsPage';
import ReclamationsPage from './pages/reclamations/ReclamationsPage';
import TableauxDeBordPage from './pages/dashboard/TableauxDeBordPage';
import AuditPage from './pages/audit/AuditPage';
import CorpusPage from './pages/corpus/CorpusPage';
import ModelesOcrPage from './pages/ocr/ModelesOcrPage';
import UtilisateursPage from './pages/utilisateurs/UtilisateursPage';
import AbsencesPage from './pages/absences/AbsencesPage';
import MesModulesPage from './pages/enseignant/MesModulesPage';
import { NAVIGATION } from './layout/navigation';

const ROLES_REFERENTIELS = ['agent_scolarite', 'chef_departement', 'responsable_academique', 'directeur', 'admin'];
const ROLES_ABSENCES = ['agent_scolarite', 'enseignant', 'admin'];
const ROLES_PV = ['agent_scolarite', 'chef_departement', 'responsable_academique', 'directeur', 'admin'];
const ROLES_VALIDATION = ['chef_departement', 'responsable_academique'];
const ROLES_RECLAMATIONS_STAFF = ['agent_scolarite', 'chef_departement', 'responsable_academique', 'admin'];
const ROLES_DASHBOARD = ['chef_departement', 'responsable_academique', 'directeur'];
const ROLES_AUDIT = ['admin', 'directeur'];
const ROLES_CORPUS = ['agent_scolarite', 'admin'];
const ECRANS_A_VENIR = NAVIGATION.filter((item) => !item.implemente);

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

          <Route
            path="/pv"
            element={(
              <ProtectedRoute roles={ROLES_PV}>
                <PvListPage />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/pv/:id"
            element={(
              <ProtectedRoute roles={ROLES_PV}>
                <PvDetailPage />
              </ProtectedRoute>
            )}
          />

          <Route
            path="/validation"
            element={(
              <ProtectedRoute roles={ROLES_VALIDATION}>
                <PvListPage
                  statutFixe="en_validation"
                  titre="Dossiers à valider"
                  description="Procès-verbaux entièrement vérifiés, en attente de votre décision."
                />
              </ProtectedRoute>
            )}
          />

          <Route
            path="/mon-profil"
            element={(
              <ProtectedRoute roles={['etudiant']}>
                <MonProfilPage />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/mes-notes"
            element={(
              <ProtectedRoute roles={['etudiant']}>
                <MesNotesPage />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/mes-reclamations"
            element={(
              <ProtectedRoute roles={['etudiant']}>
                <MesReclamationsPage />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/reclamations"
            element={(
              <ProtectedRoute roles={ROLES_RECLAMATIONS_STAFF}>
                <ReclamationsPage />
              </ProtectedRoute>
            )}
          />

          <Route
            path="/tableaux-de-bord"
            element={(
              <ProtectedRoute roles={ROLES_DASHBOARD}>
                <TableauxDeBordPage />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/audit"
            element={(
              <ProtectedRoute roles={ROLES_AUDIT}>
                <AuditPage />
              </ProtectedRoute>
            )}
          />

          <Route
            path="/corpus"
            element={(
              <ProtectedRoute roles={ROLES_CORPUS}>
                <CorpusPage />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/modeles-ocr"
            element={(
              <ProtectedRoute roles={['admin']}>
                <ModelesOcrPage />
              </ProtectedRoute>
            )}
          />
          <Route
            path="/utilisateurs"
            element={(
              <ProtectedRoute roles={['admin']}>
                <UtilisateursPage />
              </ProtectedRoute>
            )}
          />

          <Route
            path="/absences"
            element={(
              <ProtectedRoute roles={ROLES_ABSENCES}>
                <AbsencesPage />
              </ProtectedRoute>
            )}
          />

          <Route
            path="/mes-modules"
            element={(
              <ProtectedRoute roles={['enseignant']}>
                <MesModulesPage />
              </ProtectedRoute>
            )}
          />

          {ECRANS_A_VENIR.map((item) => (
            <Route key={item.to} path={item.to} element={<ComingSoonPage />} />
          ))}
          <Route path="*" element={<ComingSoonPage />} />
        </Route>
      </Routes>
    </AuthProvider>
  );
}
