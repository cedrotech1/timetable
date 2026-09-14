import { Link } from 'react-router-dom';
import { appPath, loginPath } from '../utils/appPaths';

export const NotFound = () => (
  <div className="min-h-screen flex items-center justify-center bg-[#eef1f6] px-4">
    <div className="text-center">
      <p className="text-sm font-semibold text-[#00628b]">404</p>
      <h1 className="mt-2 text-2xl font-bold text-gray-900">Page not found</h1>
      <p className="mt-2 text-sm text-gray-500">The page you requested does not exist.</p>
      <div className="mt-6 flex justify-center gap-3">
        <Link to={loginPath()} className="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-700">
          Sign in
        </Link>
        <Link
          to={appPath('dashboard')}
          className="px-4 py-2 rounded-lg bg-[#00628b] text-white text-sm"
        >
          Dashboard
        </Link>
      </div>
    </div>
  </div>
);
