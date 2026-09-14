import { useNavigate, useLocation } from 'react-router-dom';
import {
  LayoutDashboard,
  Building2,
  Layers,
  DoorOpen,
  Users,
  LogOut,
  CalendarRange,
  CalendarPlus,
  CalendarDays,
  Settings,
} from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { UrLogo } from './UrLogo';
import { appPath, loginPath } from '../utils/appPaths';
import { canManageOrg, isAdmin } from '../utils/roles';

const SidebarItem = ({ icon, label, active = false, collapsed = false, onClick }) => (
  <button
    type="button"
    className={`
      w-full flex items-center transition-colors duration-200 rounded-lg px-4 py-3 text-left
      ${collapsed ? 'lg:justify-center lg:px-2' : ''}
      ${active ? 'bg-[#00628b] text-white' : 'text-gray-700 hover:bg-gray-100'}
    `}
    onClick={onClick}
    title={collapsed ? label : undefined}
  >
    <span className={`w-5 h-5 shrink-0 mr-3 ${collapsed ? 'lg:mr-0' : ''}`}>{icon}</span>
    <span className={`font-medium text-sm leading-snug ${collapsed ? 'lg:hidden' : ''}`}>{label}</span>
  </button>
);

export const Sidebar = ({ collapsed = false }) => {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuth();

  const items = [
    { id: 'dashboard', label: 'Dashboard', icon: <LayoutDashboard size={20} />, path: appPath('dashboard') },
    {
      id: 'organization',
      label: 'Organizational structure',
      icon: <Building2 size={20} />,
      path: appPath('organization'),
      match: ['/organization', '/campuses', '/colleges', '/schools', '/programs'],
    },
    { id: 'modules', label: 'Modules', icon: <Layers size={20} />, path: appPath('modules') },
    {
      id: 'facilities',
      label: 'Facilities',
      icon: <DoorOpen size={20} />,
      path: appPath('facilities'),
      // Exact facilities list only — calendar has its own menu item
      exactOnly: true,
    },
    {
      id: 'facility-calendar',
      label: 'Facility calendar',
      icon: <CalendarDays size={20} />,
      path: appPath('facilities/calendar'),
      match: ['/facilities/calendar', '/calendar'],
    },
    { id: 'timetables', label: 'General timetable', icon: <CalendarRange size={20} />, path: appPath('timetables') },
  ];

  if (canManageOrg(user?.role)) {
    items.push({
      id: 'set-timetable',
      label: 'Set timetable',
      icon: <CalendarPlus size={20} />,
      path: appPath('set-timetable'),
    });
    items.push({
      id: 'settings',
      label: 'System settings',
      icon: <Settings size={20} />,
      path: appPath('settings'),
    });
  }

  if (isAdmin(user?.role)) {
    items.push({ id: 'users', label: 'Users', icon: <Users size={20} />, path: appPath('users') });
  }

  const handleLogout = () => {
    logout();
    navigate(loginPath());
  };

  const isActive = (item) => {
    const path = location.pathname;
    if (item.exactOnly) {
      return path === item.path || path === `${item.path}/`;
    }
    if (item.match) {
      return item.match.some((m) => path.includes(m));
    }
    if (path === item.path || path.startsWith(`${item.path}/`)) return true;
    return false;
  };

  return (
    <div className="h-full flex flex-col bg-white">
      <div className={`flex items-center gap-3 border-b border-gray-100 px-4 py-4 ${collapsed ? 'lg:justify-center' : ''}`}>
        <div className="h-10 w-10 shrink-0">
          <UrLogo />
        </div>
        <div className={collapsed ? 'lg:hidden' : ''}>
          <p className="m-0 text-sm font-bold text-[#1e3c72] leading-tight">UR Timetable</p>
          <p className="m-0 text-[11px] text-gray-500">Academic scheduling</p>
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto p-3 space-y-1">
        {items.map((item) => (
          <SidebarItem
            key={item.id}
            icon={item.icon}
            label={item.label}
            collapsed={collapsed}
            active={isActive(item)}
            onClick={() => navigate(item.path)}
          />
        ))}
      </nav>

      <div className="p-3 border-t border-gray-100">
        {!collapsed && canManageOrg(user?.role) && (
          <p className="px-2 mb-2 text-[11px] text-gray-400 uppercase tracking-wide">
            Manage access enabled
          </p>
        )}
        <SidebarItem
          icon={<LogOut size={20} />}
          label="Sign out"
          collapsed={collapsed}
          onClick={handleLogout}
        />
      </div>
    </div>
  );
};
