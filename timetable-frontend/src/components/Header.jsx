import { useState, useRef, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Menu, LogOut, ChevronDown, User } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { UserAvatar } from './UserAvatar';
import { appPath, loginPath } from '../utils/appPaths';
import { roleLabel } from '../utils/roles';
import { capitalizePersonName } from '../utils/formatDisplay';

export const Header = ({ onMenuClick }) => {
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const menuRef = useRef(null);

  useEffect(() => {
    if (!userMenuOpen) return undefined;
    const onDocClick = (event) => {
      if (menuRef.current && !menuRef.current.contains(event.target)) {
        setUserMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', onDocClick);
    return () => document.removeEventListener('mousedown', onDocClick);
  }, [userMenuOpen]);

  const handleLogout = () => {
    logout();
    navigate(loginPath());
  };

  return (
    <header className="h-16 bg-white border-b border-gray-200 px-4 lg:px-6 flex items-center justify-between shadow-sm">
      <div className="flex items-center gap-3 min-w-0">
        <button
          type="button"
          onClick={onMenuClick}
          className="p-2 rounded-lg text-gray-600 hover:bg-gray-100"
          aria-label="Toggle menu"
        >
          <Menu size={20} />
        </button>
        <div className="min-w-0">
          <p className="m-0 text-sm font-semibold text-gray-900 truncate">Timetable Management</p>
          <p className="m-0 text-xs text-gray-500 truncate hidden sm:block">
            Organizational structure · Modules · Facilities
          </p>
        </div>
      </div>

      <div className="relative" ref={menuRef}>
        <button
          type="button"
          onClick={() => setUserMenuOpen((v) => !v)}
          className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50"
        >
          <UserAvatar user={user} size={36} />
          <div className="hidden sm:block text-left min-w-0">
            <p className="m-0 text-sm font-medium text-gray-900 truncate max-w-[160px]">
              {capitalizePersonName(user?.names) || 'User'}
            </p>
            <p className="m-0 text-xs text-gray-500 truncate">{roleLabel(user?.role)}</p>
          </div>
          <ChevronDown size={16} className="text-gray-400" />
        </button>

        {userMenuOpen && (
          <div className="absolute right-0 mt-2 w-56 rounded-xl border border-gray-200 bg-white shadow-lg py-1 z-50">
            <button
              type="button"
              className="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"
              onClick={() => {
                setUserMenuOpen(false);
                navigate(appPath('profile'));
              }}
            >
              <User size={16} />
              Profile
            </button>
            <button
              type="button"
              className="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50"
              onClick={handleLogout}
            >
              <LogOut size={16} />
              Sign out
            </button>
          </div>
        )}
      </div>
    </header>
  );
};
