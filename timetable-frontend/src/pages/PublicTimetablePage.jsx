import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import * as XLSX from 'xlsx';
import { CalendarRange, Download, RotateCcw, Search } from 'lucide-react';
import { timetableService } from '../services/api';
import { useNotification } from '../contexts/NotificationContext';
import { UrLogo } from '../components/UrLogo';
import { loginPath, appPath } from '../utils/appPaths';
import { useAuth } from '../contexts/AuthContext';
import { formatPlanLecturers } from '../utils/formatPlanLecturers';
import { capitalizeCampusName } from '../utils/formatDisplay';
import { fmtTime } from '../utils/timeFormat';

const DAY_ORDER = {
  Monday: 1,
  Tuesday: 2,
  Wednesday: 3,
  Thursday: 4,
  Friday: 5,
  Saturday: 6,
  Sunday: 7,
};

function setSelectOptions(items, getId, getText) {
  const map = new Map();
  for (const item of items) {
    const id = getId(item);
    if (id == null || id === '') continue;
    if (!map.has(String(id))) map.set(String(id), { id, text: getText(item) });
  }
  return [...map.values()].sort((a, b) => String(a.text).localeCompare(String(b.text)));
}

/** Public homepage — all live-period timetables with student filters (like PHP index.php). */
export default function PublicTimetablePage() {
  const { showError, showSuccess } = useNotification();
  const { isAuthenticated } = useAuth();

  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);

  const [filters, setFilters] = useState({
    college: '',
    school: '',
    program: '',
    yearOfStudy: '',
    group: '',
    campus: '',
  });
  const [search, setSearch] = useState({ lecturer: '', facility: '', module: '' });
  const [appliedSearch, setAppliedSearch] = useState({ lecturer: '', facility: '', module: '' });

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await timetableService.listPublic();
      setRows(res?.data || []);
      setMeta(res?.meta || null);
    } catch (error) {
      showError(error.response?.data?.message || 'Failed to load timetable');
    } finally {
      setLoading(false);
    }
  }, [showError]);

  useEffect(() => {
    load();
  }, [load]);

  const allGroups = useMemo(() => rows.flatMap((t) => t.groups || []), [rows]);

  const collegeOptions = useMemo(
    () => setSelectOptions(allGroups, (g) => g.college_id, (g) => g.college),
    [allGroups]
  );
  const schoolOptions = useMemo(() => {
    const source = filters.college
      ? allGroups.filter((g) => String(g.college_id) === String(filters.college))
      : allGroups;
    return setSelectOptions(source, (g) => g.school_id, (g) => g.school);
  }, [allGroups, filters.college]);
  const programOptions = useMemo(() => {
    let source = allGroups;
    if (filters.college) source = source.filter((g) => String(g.college_id) === String(filters.college));
    if (filters.school) source = source.filter((g) => String(g.school_id) === String(filters.school));
    return setSelectOptions(source, (g) => g.program_id, (g) => g.program);
  }, [allGroups, filters.college, filters.school]);
  const campusOptions = useMemo(
    () => setSelectOptions(allGroups, (g) => g.campus_id, (g) => capitalizeCampusName(g.campus) || g.campus),
    [allGroups]
  );
  const yearOptions = useMemo(
    () =>
      setSelectOptions(
        allGroups,
        (g) => g.year_of_study,
        (g) => `Year ${g.year_of_study}`
      ),
    [allGroups]
  );
  const groupOptions = useMemo(() => {
    let source = allGroups;
    if (filters.college) source = source.filter((g) => String(g.college_id) === String(filters.college));
    if (filters.school) source = source.filter((g) => String(g.school_id) === String(filters.school));
    if (filters.program) source = source.filter((g) => String(g.program_id) === String(filters.program));
    if (filters.yearOfStudy)
      source = source.filter((g) => String(g.year_of_study) === String(filters.yearOfStudy));
    if (filters.campus) source = source.filter((g) => String(g.campus_id) === String(filters.campus));
    return setSelectOptions(source, (g) => g.id, (g) => g.name);
  }, [allGroups, filters]);

  const filtered = useMemo(() => {
    const lecturerQ = appliedSearch.lecturer.trim().toLowerCase();
    const facilityQ = appliedSearch.facility.trim().toLowerCase();
    const moduleQ = appliedSearch.module.trim().toLowerCase();

    return rows
      .filter((t) => {
        if (lecturerQ) {
          const leaderHit =
            t.leader_lecturer &&
            [t.leader_lecturer.names, t.leader_lecturer.email]
              .filter(Boolean)
              .some((v) => String(v).toLowerCase().includes(lecturerQ));
          const otherHit = (t.other_lecturers || []).some((l) =>
            [l.names, l.email].filter(Boolean).some((v) => String(v).toLowerCase().includes(lecturerQ))
          );
          if (!leaderHit && !otherHit) return false;
        }
        if (facilityQ) {
          const hay = `${t.facility?.name || ''} ${t.facility?.campus?.name || ''}`.toLowerCase();
          if (!hay.includes(facilityQ)) return false;
        }
        if (moduleQ) {
          const hay = `${t.course || ''} ${t.code || ''}`.toLowerCase();
          if (!hay.includes(moduleQ)) return false;
        }
        const groups = t.groups || [];
        if (!groups.length) {
          return !(filters.college || filters.school || filters.program || filters.yearOfStudy || filters.group || filters.campus);
        }
        return groups.some((g) => {
          if (filters.college && String(g.college_id) !== String(filters.college)) return false;
          if (filters.school && String(g.school_id) !== String(filters.school)) return false;
          if (filters.program && String(g.program_id) !== String(filters.program)) return false;
          if (filters.yearOfStudy && String(g.year_of_study) !== String(filters.yearOfStudy)) return false;
          if (filters.group && String(g.id) !== String(filters.group)) return false;
          if (filters.campus && String(g.campus_id) !== String(filters.campus)) return false;
          return true;
        });
      })
      .map((t) => {
        const groups = (t.groups || []).filter((g) => {
          if (filters.college && String(g.college_id) !== String(filters.college)) return false;
          if (filters.school && String(g.school_id) !== String(filters.school)) return false;
          if (filters.program && String(g.program_id) !== String(filters.program)) return false;
          if (filters.yearOfStudy && String(g.year_of_study) !== String(filters.yearOfStudy)) return false;
          if (filters.group && String(g.id) !== String(filters.group)) return false;
          if (filters.campus && String(g.campus_id) !== String(filters.campus)) return false;
          return true;
        });
        return { ...t, groups: groups.length ? groups : t.groups || [] };
      })
      .sort((a, b) => {
        const sa = a.sessions?.[0];
        const sb = b.sessions?.[0];
        if (!sa && !sb) return a.id - b.id;
        if (!sa) return 1;
        if (!sb) return -1;
        const da = DAY_ORDER[sa.day] || 99;
        const db = DAY_ORDER[sb.day] || 99;
        if (da !== db) return da - db;
        return String(sa.start_time || '').localeCompare(String(sb.start_time || ''));
      });
  }, [rows, filters, appliedSearch]);

  const resetFilters = () => {
    setFilters({ college: '', school: '', program: '', yearOfStudy: '', group: '', campus: '' });
    setSearch({ lecturer: '', facility: '', module: '' });
    setAppliedSearch({ lecturer: '', facility: '', module: '' });
  };

  const exportExcel = () => {
    const exportRows = [];
    for (const t of filtered) {
      const sessions = t.sessions?.length ? t.sessions : [{ day: '', start_time: '', end_time: '' }];
      const groups = t.groups?.length ? t.groups : [{}];
      for (const s of sessions) {
        for (const g of groups) {
          exportRows.push({
            Day: s.day || '',
            Time: s.start_time ? `${fmtTime(s.start_time)}-${fmtTime(s.end_time)}` : '',
            Course: t.course || '',
            Credits: t.credits ?? '',
            Facility: t.facility?.name || '',
            Group: g.name || '',
            'Year of Study': g.year_of_study || '',
            Program: g.program || '',
            School: g.school || '',
            Campus: capitalizeCampusName(g.campus) || '',
            College: g.college || '',
            Lecturers: formatPlanLecturers(t),
          });
        }
      }
    }
    const ws = XLSX.utils.json_to_sheet(exportRows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Timetable');
    XLSX.writeFile(wb, 'ur-timetable.xlsx');
    showSuccess(`Exported ${exportRows.length} row(s)`);
  };

  return (
    <div className="min-h-screen bg-[#f4f6f8]">
      <header className="sticky top-0 z-40 bg-[#031f50] text-white shadow-md">
        <div className="max-w-[95rem] mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 bg-white rounded-full p-1">
              <UrLogo />
            </div>
            <div>
              <div className="font-bold text-base sm:text-lg leading-tight">University of Rwanda</div>
              <div className="text-sm opacity-90">Public timetable</div>
            </div>
          </div>
          <div className="flex items-center gap-2 text-sm">
            {isAuthenticated ? (
              <Link
                to={appPath('dashboard')}
                className="px-3 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 font-medium"
              >
                Staff dashboard
              </Link>
            ) : (
              <Link
                to={loginPath()}
                className="px-3 py-1.5 rounded-lg bg-white text-[#031f50] font-semibold hover:bg-gray-100"
              >
                Staff login
              </Link>
            )}
          </div>
        </div>
      </header>

      <main className="max-w-[95rem] mx-auto px-4 py-5 space-y-4">
        <div className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold text-[#031f50] m-0 flex items-center gap-2">
              <CalendarRange size={22} className="text-[#00628b]" />
              Teaching timetable
            </h1>
            <p className="mt-1 mb-0 text-base text-gray-600">
              Academic year{' '}
              <strong>{meta?.yearLabel || meta?.academicYearId || '—'}</strong>
              {' · '}Semester <strong>{meta?.semester || '—'}</strong>
              {' · '}All plans shown by default — use filters to narrow.
            </p>
          </div>
          <button
            type="button"
            onClick={exportExcel}
            disabled={!filtered.length}
            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border bg-white text-sm font-medium disabled:opacity-50"
          >
            <Download size={14} /> Export Excel
          </button>
        </div>

        <div className="bg-white rounded-xl border p-3 shadow-sm space-y-3">
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
            {[
              ['campus', 'Campus', campusOptions],
              ['college', 'College', collegeOptions],
              ['school', 'School', schoolOptions],
              ['program', 'Program', programOptions],
              ['yearOfStudy', 'Year', yearOptions],
              ['group', 'Group', groupOptions],
            ].map(([key, label, opts]) => (
              <label key={key} className="block">
                <span className="block text-xs uppercase tracking-wide text-gray-500 mb-1">{label}</span>
                <select
                  value={filters[key]}
                  onChange={(e) => setFilters((p) => ({ ...p, [key]: e.target.value }))}
                  className="w-full rounded-lg border border-gray-200 px-2.5 py-2 text-sm"
                >
                  <option value="">All</option>
                  {opts.map((o) => (
                    <option key={o.id} value={o.id}>
                      {o.text}
                    </option>
                  ))}
                </select>
              </label>
            ))}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
            {[
              ['module', 'Module'],
              ['facility', 'Facility'],
              ['lecturer', 'Lecturer'],
            ].map(([key, ph]) => (
              <div key={key} className="relative">
                <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  value={search[key]}
                  onChange={(e) => setSearch((p) => ({ ...p, [key]: e.target.value }))}
                  onKeyDown={(e) => e.key === 'Enter' && setAppliedSearch({ ...search })}
                  placeholder={ph}
                  className="w-full rounded-lg border border-gray-200 pl-8 pr-2 py-2 text-sm"
                />
              </div>
            ))}
          </div>

          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => setAppliedSearch({ ...search })}
              className="px-3 py-2 rounded-lg bg-[#00628b] text-white text-sm font-semibold"
            >
              Apply search
            </button>
            <button
              type="button"
              onClick={resetFilters}
              className="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-[#031f50] text-white text-sm font-semibold"
            >
              <RotateCcw size={14} /> Reset
            </button>
          </div>
        </div>

        <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
          {loading ? (
            <div className="py-16 text-center text-gray-500 text-base">Loading timetable…</div>
          ) : filtered.length === 0 ? (
            <div className="py-16 text-center text-gray-500 text-base">No teaching plans match these filters.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm border-collapse">
                <thead>
                  <tr>
                    {['Day', 'Time', 'Course', 'Credits', 'Facility'].map((h) => (
                      <th
                        key={h}
                        rowSpan={2}
                        className="border px-2.5 py-2 text-white text-sm font-semibold"
                        style={{ background: 'rgb(99,124,167)' }}
                      >
                        {h}
                      </th>
                    ))}
                    <th
                      colSpan={6}
                      className="border px-2.5 py-2 text-white text-center text-sm font-semibold"
                      style={{ background: 'rgb(99,124,167)' }}
                    >
                      Group details
                    </th>
                    <th
                      rowSpan={2}
                      className="border px-2.5 py-2 text-white text-sm font-semibold"
                      style={{ background: 'rgb(99,124,167)' }}
                    >
                      Lecturers
                    </th>
                  </tr>
                  <tr>
                    {['Group', 'Year', 'Program', 'School', 'Campus', 'College'].map((h) => (
                      <th
                        key={h}
                        className="border px-2.5 py-2 text-white text-sm font-semibold"
                        style={{ background: 'rgb(99,124,167)' }}
                      >
                        {h}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((t) => {
                    const first = t.sessions?.[0];
                    const lecturers = formatPlanLecturers(t);
                    const groups = t.groups?.length ? t.groups : [null];
                    return groups.map((g, gi) => (
                      <tr key={`${t.id}-${gi}`} className={gi % 2 === 0 ? 'bg-white' : 'bg-[#f8f9fa]'}>
                        {gi === 0 && (
                          <>
                            <td rowSpan={groups.length} className="border px-2.5 py-2">
                              {first?.day || '—'}
                            </td>
                            <td rowSpan={groups.length} className="border px-2.5 py-2 whitespace-nowrap">
                              {first ? `${fmtTime(first.start_time)}-${fmtTime(first.end_time)}` : '—'}
                            </td>
                            <td rowSpan={groups.length} className="border px-2.5 py-2 text-left">
                              {t.course || '—'}
                            </td>
                            <td rowSpan={groups.length} className="border px-2.5 py-2 text-center">
                              {t.credits ?? '—'}
                            </td>
                            <td rowSpan={groups.length} className="border px-2.5 py-2">
                              <div className="font-medium">{t.facility?.name || '—'}</div>
                              {t.facility?.buildName &&
                              String(t.facility.buildName).trim().toLowerCase() !==
                                String(t.facility.name || '').trim().toLowerCase() ? (
                                <div className="text-xs text-slate-500 mt-0.5">{t.facility.buildName}</div>
                              ) : null}
                            </td>
                          </>
                        )}
                        <td className="border px-2.5 py-2">{g?.name || '—'}</td>
                        <td className="border px-2.5 py-2 text-center">{g?.year_of_study || '—'}</td>
                        <td className="border px-2.5 py-2 text-left">{g?.program || '—'}</td>
                        <td className="border px-2.5 py-2 text-left">{g?.school || '—'}</td>
                        <td className="border px-2.5 py-2">{capitalizeCampusName(g?.campus) || '—'}</td>
                        <td className="border px-2.5 py-2">{g?.college || '—'}</td>
                        {gi === 0 && (
                          <td rowSpan={groups.length} className="border px-2.5 py-2 text-left">
                            {lecturers || '—'}
                          </td>
                        )}
                      </tr>
                    ));
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
        <p className="text-sm text-gray-500 m-0">
          Showing {filtered.length} of {rows.length} plan(s) for the live academic period.
        </p>
      </main>
    </div>
  );
}
