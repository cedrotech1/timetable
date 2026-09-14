import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Building2, Layers, DoorOpen, Users, CalendarRange, CalendarPlus } from 'lucide-react';
import {
  organizationService,
  facilitiesService,
  usersService,
  timetableService,
} from '../services/api';
import { appPath } from '../utils/appPaths';
import { useAuth } from '../contexts/AuthContext';
import { canManageOrg, isAdmin } from '../utils/roles';

export default function DashboardPage() {
  const { user } = useAuth();
  const [stats, setStats] = useState({
    campuses: 0,
    colleges: 0,
    schools: 0,
    programs: 0,
    modules: 0,
    facilities: 0,
    timetables: 0,
    users: null,
  });

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const [org, facilities, timetables] = await Promise.all([
          organizationService.getStructure(),
          facilitiesService.getAll(),
          timetableService.list(),
        ]);
        if (cancelled) return;
        const counts = org?.data?.counts || {};
        setStats((prev) => ({
          ...prev,
          campuses: counts.campuses || 0,
          colleges: counts.colleges || 0,
          schools: counts.schools || 0,
          programs: counts.programs || 0,
          modules: counts.modules || 0,
          facilities: facilities?.success ? (facilities.data || []).length : 0,
          timetables: timetables?.success ? (timetables.data || []).length : 0,
        }));
      } catch {
        // ignore
      }

      if (isAdmin(user?.role)) {
        try {
          const users = await usersService.getAll();
          if (!cancelled && users?.success) {
            setStats((prev) => ({ ...prev, users: (users.data || []).length }));
          }
        } catch {
          // ignore
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [user?.role]);

  const cards = [
    {
      key: 'organization',
      label: 'Organizational structure',
      detail: `${stats.colleges} colleges · ${stats.schools} schools · ${stats.programs} programs`,
      icon: Building2,
      path: 'organization',
      value: stats.campuses,
      valueLabel: 'campuses',
    },
    {
      key: 'modules',
      label: 'Modules',
      detail: 'Course modules by program',
      icon: Layers,
      path: 'modules',
      value: stats.modules,
    },
    {
      key: 'facilities',
      label: 'Facilities',
      detail: 'Classrooms and teaching spaces',
      icon: DoorOpen,
      path: 'facilities',
      value: stats.facilities,
    },
    {
      key: 'timetables',
      label: 'General timetable',
      detail: 'Filter and browse saved teaching plans',
      icon: CalendarRange,
      path: 'timetables',
      value: stats.timetables,
    },
  ];

  if (canManageOrg(user?.role)) {
    cards.push({
      key: 'set-timetable',
      label: 'Set timetable',
      detail: 'Single · Bulk · Excel upload',
      icon: CalendarPlus,
      path: 'set-timetable',
      value: '→',
      valueLabel: 'create',
    });
  }

  if (isAdmin(user?.role)) {
    cards.push({
      key: 'users',
      label: 'Users',
      detail: 'Staff accounts',
      icon: Users,
      path: 'users',
      value: stats.users ?? '—',
    });
  }

  return (
    <div className="max-w-7xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900 m-0">Dashboard</h1>
        <p className="mt-1 mb-0 text-sm text-gray-500">
          Welcome{user?.names ? `, ${user.names}` : ''} — manage the academic structure used by the timetable.
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        {cards.map((card) => {
          const Icon = card.icon;
          return (
            <Link
              key={card.key}
              to={appPath(card.path)}
              className="group bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:border-[#00628b]/40 hover:shadow-md transition"
            >
              <div className="flex items-start justify-between">
                <div className="h-11 w-11 rounded-xl bg-[#e8f4f8] text-[#00628b] flex items-center justify-center group-hover:bg-[#00628b] group-hover:text-white transition">
                  <Icon size={22} />
                </div>
                <div className="text-right">
                  <span className="text-2xl font-bold text-gray-900">{card.value}</span>
                  {card.valueLabel ? (
                    <p className="m-0 text-[11px] text-gray-400 uppercase tracking-wide">{card.valueLabel}</p>
                  ) : null}
                </div>
              </div>
              <p className="mt-4 mb-0 text-sm font-semibold text-gray-800">{card.label}</p>
              <p className="mt-1 mb-0 text-xs text-gray-500">{card.detail}</p>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
