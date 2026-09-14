import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Eye, EyeOff } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { UrLogo } from '../components/UrLogo';
import { PublicSplitLayout } from '../components/PublicSplitLayout';
import { appPath } from '../utils/appPaths';

export const Login = () => {
  const navigate = useNavigate();
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const result = await login({ email, password });
      if (result.success) {
        navigate(appPath('dashboard'));
      } else {
        setError(result.message || 'Login failed');
      }
    } catch {
      setError('An unexpected error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <PublicSplitLayout
      visualTitle="UR Timetable"
      visualText="Sign in to manage campuses, colleges, schools, programs, modules, and teaching facilities."
    >
      <div className="flex items-center gap-3 mb-6">
        <div className="h-12 w-12 shrink-0">
          <UrLogo />
        </div>
        <div>
          <h4 className="m-0 text-[0.95rem] font-bold text-[#1e3c72] leading-tight">University of Rwanda</h4>
          <p className="m-0 text-xs text-gray-500">Timetable Management System</p>
        </div>
      </div>

      <h1 className="text-[1.75rem] sm:text-2xl font-bold text-center text-gray-900 mb-6">Sign in</h1>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="you@ur.ac.rw"
            className="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/30"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <div className="relative">
            <input
              type={showPassword ? 'text' : 'password'}
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-lg border border-gray-200 px-3 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/30"
            />
            <button
              type="button"
              className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
              onClick={() => setShowPassword((v) => !v)}
            >
              {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          </div>
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full rounded-lg bg-[#00628b] text-white py-2.5 text-sm font-semibold hover:bg-[#004f70] disabled:opacity-60"
        >
          {loading ? 'Signing in…' : 'Sign in'}
        </button>
      </form>

      <p className="mt-6 text-center text-xs text-gray-500">
        <Link to="/" className="text-[#00628b] hover:underline">
          View public timetable
        </Link>
        {' · '}Need help? Contact the timetable office
      </p>
    </PublicSplitLayout>
  );
};
