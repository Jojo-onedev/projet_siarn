import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import { ProtectedRoute } from './auth/ProtectedRoute';
import { AppShell } from './layout/AppShell';
import LoginPage from './pages/LoginPage';
import MfaVerificationPage from './pages/MfaVerificationPage';
import MfaEnrollmentPage from './pages/MfaEnrollmentPage';
import DashboardHomePage from './pages/DashboardHomePage';
import ComingSoonPage from './pages/ComingSoonPage';
import { NAVIGATION } from './layout/navigation';

const ECRANS_A_VENIR = NAVIGATION.filter((item) => item.to !== '/');

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
          {ECRANS_A_VENIR.map((item) => (
            <Route key={item.to} path={item.to} element={<ComingSoonPage />} />
          ))}
          <Route path="*" element={<ComingSoonPage />} />
        </Route>
      </Routes>
    </AuthProvider>
  );
}
