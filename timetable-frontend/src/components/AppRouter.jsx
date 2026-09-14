import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { DashboardLayout } from './DashboardLayout';
import { ProtectedRoute, PublicRoute } from './ProtectedRoute';
import { Login } from '../pages/Login';
import DashboardPage from '../pages/DashboardPage';
import OrganizationStructurePage from '../pages/OrganizationStructurePage';
import ModulesPage from '../pages/ModulesPage';
import FacilitiesPage from '../pages/FacilitiesPage';
import FacilityCalendarPage from '../pages/FacilityCalendarPage';
import UsersPage from '../pages/UsersPage';
import ProfilePage from '../pages/ProfilePage';
import SetTimetablePage from '../pages/SetTimetablePage';
import TimetablesPage from '../pages/TimetablesPage';
import PublicTimetablePage from '../pages/PublicTimetablePage';
import SystemSettingsPage from '../pages/SystemSettingsPage';
import { NotFound } from '../pages/NotFound';
import { appPath, loginPath, routerBasename } from '../utils/appPaths';

export const AppRouter = () => (
  <Router basename={routerBasename() === '/' ? undefined : routerBasename()}>
    <Routes>
      <Route path="/" element={<PublicTimetablePage />} />

      <Route
        path={loginPath()}
        element={
          <PublicRoute>
            <Login />
          </PublicRoute>
        }
      />

      <Route
        path={appPath()}
        element={
          <ProtectedRoute>
            <DashboardLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to={appPath('dashboard')} replace />} />
        <Route path="dashboard" element={<DashboardPage />} />
        <Route path="organization" element={<OrganizationStructurePage />} />
        <Route path="campuses" element={<Navigate to={appPath('organization')} replace />} />
        <Route path="colleges" element={<Navigate to={appPath('organization')} replace />} />
        <Route path="schools" element={<Navigate to={appPath('organization')} replace />} />
        <Route path="programs" element={<Navigate to={appPath('organization')} replace />} />
        <Route path="modules" element={<ModulesPage />} />
        <Route path="facilities" element={<FacilitiesPage />} />
        <Route path="facilities/calendar" element={<FacilityCalendarPage />} />
        <Route path="facilities/:id/calendar" element={<FacilityCalendarPage />} />
        <Route path="timetables" element={<TimetablesPage />} />
        <Route path="set-timetable" element={<SetTimetablePage />} />
        <Route path="settings" element={<SystemSettingsPage />} />
        <Route path="users" element={<UsersPage />} />
        <Route path="profile" element={<ProfilePage />} />
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
  </Router>
);
