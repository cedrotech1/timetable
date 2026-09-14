import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { appPath, loginPath } from '../utils/appPaths';

export const ProtectedRoute = ({ children }) => {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#00628b]" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to={loginPath()} replace />;
  }

  return children;
};

export const PublicRoute = ({ children }) => {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-100">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#00628b]" />
      </div>
    );
  }

  if (isAuthenticated) {
    return <Navigate to={appPath('dashboard')} replace />;
  }

  return children;
};
