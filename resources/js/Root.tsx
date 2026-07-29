import { Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider, useAuth, usePermission } from './auth/AuthContext';
import LoginPage from './pages/LoginPage';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import DashboardPage from './pages/DashboardPage';
import StationListPage from './pages/StationListPage';
import StationCreatePage from './pages/StationCreatePage';
import StationDetailPage from './pages/StationDetailPage';
import StationEditPage from './pages/StationEditPage';
import { LoadingOutlined } from '@ant-design/icons';

function ProtectedRoute({ children, permission }: { children: React.ReactNode; permission?: string }) {
  const { user, loading } = useAuth();
  const hasPermission = usePermission(permission ?? '');

  if (loading) {
    return (
      <div className="loading-screen" role="status" aria-live="polite">
        <div className="loading-screen__content">
          <LoadingOutlined spin />
          <span>Restoring secure session…</span>
        </div>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (permission && !hasPermission) {
    return <Navigate to="/stations" replace />;
  }

  return <>{children}</>;
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/dashboard"
        element={
          <ProtectedRoute>
            <DashboardPage />
          </ProtectedRoute>
        }
      />
      {/* Module 2: Charging Stations */}
        <Route path="/stations" element={<ProtectedRoute permission="charging_stations.view"><StationListPage /></ProtectedRoute>} />
        <Route path="/stations/new" element={<ProtectedRoute permission="charging_stations.create"><StationCreatePage /></ProtectedRoute>} />
        <Route path="/stations/:id" element={<ProtectedRoute permission="charging_stations.view"><StationDetailPage /></ProtectedRoute>} />
        <Route path="/stations/:id/edit" element={<ProtectedRoute permission="charging_stations.update"><StationEditPage /></ProtectedRoute>} />

      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <div className="app-canvas">
        <AppRoutes />
      </div>
    </AuthProvider>
  );
}
