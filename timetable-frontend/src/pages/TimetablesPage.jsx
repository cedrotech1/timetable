import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import * as XLSX from 'xlsx';
import { CalendarRange, Download, Plus, RotateCcw, Search } from 'lucide-react';
import { timetableService } from '../services/api';
import { useNotification } from '../contexts/NotificationContext';
import { useAuth } from '../contexts/AuthContext';
import { canManageOrg } from '../utils/roles';
import { appPath } from '../utils/appPaths';
import EditTeachingPlanModal from '../components/EditTeachingPlanModal';
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

export default function TimetablesPage() {
  const { user } = useAuth();
  const { showError, showSuccess, showWarning } = useNotification();
  const canManage = canManageOrg(user?.role);

  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [editingPlan, setEditingPlan] = useState(null);

  const [filters, setFilters] = useState({
    id: '',
    college: '',
    school: '',
    program: '',
    yearOfStudy: '',
    group: '',
    campus: '',
  });
  const [search, setSearch] = useState({
    lecturer: '',
    facility: '',
    module: '',
  });
  const [appliedSearch, setAppliedSearch] = useState({
    lecturer: '',
    facility: '',
    module: '',
  });

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await timetableService.list();
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
        if (filters.id && String(t.id) !== String(filters.id)) return false;

        if (lecturerQ) {
          const leaderHit =
            t.leader_lecturer &&
            [t.leader_lecturer.names, t.leader_lecturer.email, t.leader_lecturer.phone]
              .filter(Boolean)
              .some((v) => String(v).toLowerCase().includes(lecturerQ));
          const otherHit = (t.other_lecturers || []).some((l) =>
            [l.names, l.email, l.phone].filter(Boolean).some((v) => String(v).toLowerCase().includes(lecturerQ))
          );
          if (!leaderHit && !otherHit) return false;
        }

        if (facilityQ) {
          const hay = `${t.facility?.name || ''} ${t.facility?.name2 || ''} ${t.facility?.buildCode || ''} ${t.facility?.buildName || ''} ${t.facility?.campus?.name || ''}`.toLowerCase();
          if (!hay.includes(facilityQ)) return false;
        }

        if (moduleQ) {
          const hay = `${t.course || ''} ${t.code || ''}`.toLowerCase();
          if (!hay.includes(moduleQ)) return false;
        }

        const groups = t.groups || [];
        if (!groups.length) {
          return !(
            filters.college ||
            filters.school ||
            filters.program ||
            filters.yearOfStudy ||
            filters.group ||
            filters.campus
          );
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

  const currentView = useMemo(() => {
    const lines = [];
    const ayLabel = meta?.yearLabel || (meta?.academicYearId ? `AY #${meta.academicYearId}` : null);
    if (ayLabel) lines.push(`Academic Year: ${ayLabel}`);
    if (meta?.semester != null && meta?.semester !== '') lines.push(`Semester: ${meta.semester}`);
    if (filters.campus) {
      const c = campusOptions.find((o) => String(o.id) === String(filters.campus));
      if (c) lines.push(`Campus: ${c.text}`);
    }
    if (filters.college) {
      const c = collegeOptions.find((o) => String(o.id) === String(filters.college));
      if (c) lines.push(`College: ${c.text}`);
    }
    if (filters.school) {
      const c = schoolOptions.find((o) => String(o.id) === String(filters.school));
      if (c) lines.push(`School: ${c.text}`);
    }
    if (filters.program) {
      const c = programOptions.find((o) => String(o.id) === String(filters.program));
      if (c) lines.push(`Program: ${c.text}`);
    }
    if (filters.yearOfStudy) lines.push(`Year of Study: Year ${filters.yearOfStudy}`);
    if (filters.group) {
      const c = groupOptions.find((o) => String(o.id) === String(filters.group));
      if (c) lines.push(`Group: ${c.text}`);
    }
    if (filters.id) lines.push(`ID: ${filters.id}`);
    if (appliedSearch.lecturer) lines.push(`Lecturer Search: "${appliedSearch.lecturer}"`);
    if (appliedSearch.facility) lines.push(`Facility Search: "${appliedSearch.facility}"`);
    if (appliedSearch.module) lines.push(`Module Search: "${appliedSearch.module}"`);
    return lines;
  }, [
    meta,
    filters,
    appliedSearch,
    campusOptions,
    collegeOptions,
    schoolOptions,
    programOptions,
    groupOptions,
  ]);

  const resetFilters = () => {
    setFilters({
      id: '',
      college: '',
      school: '',
      program: '',
      yearOfStudy: '',
      group: '',
      campus: '',
    });
    setSearch({ lecturer: '', facility: '', module: '' });
    setAppliedSearch({ lecturer: '', facility: '', module: '' });
  };

  const exportExcel = () => {
    const exportRows = [];
    for (const t of filtered) {
      const sessions = t.sessions?.length
        ? t.sessions
        : [{ day: '', start_time: '', end_time: '' }];
      const groups = t.groups?.length ? t.groups : [{}];
      for (const s of sessions) {
        for (const g of groups) {
          exportRows.push({
            ID: t.id,
            Day: s.day || '',
            Time: s.start_time ? `${fmtTime(s.start_time)}-${fmtTime(s.end_time)}` : '',
            Course: t.course || '',
            Code: t.code || '',
            Credits: t.credits ?? '',
            Facility: t.facility?.name || '',
            'Facility code': t.facility?.buildCode || '',
            Building: t.facility?.buildName || '',
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
    XLSX.writeFile(wb, 'timetable.xlsx');
    showSuccess(`Exported ${exportRows.length} row(s)`);
  };

  return (
    <div className="max-w-[95rem] mx-auto space-y-4">
      <div className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[#031f50] m-0 flex items-center gap-2">
            <CalendarRange size={22} className="text-[#00628b]" />
            General timetable
          </h1>
          <p className="mt-1 mb-0 text-base text-gray-500">
            Filter and browse saved teaching plans from single, bulk, or Excel save.
            {meta?.yearLabel || meta?.academicYearId
              ? ` · ${meta.yearLabel || `AY #${meta.academicYearId}`} · Semester ${meta.semester}`
              : ''}
            {canManage ? ' · Click a row to view / edit.' : ''}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {canManage && (
            <Link
              to={appPath('set-timetable')}
              className="inline-flex items-center gap-2 rounded-lg bg-[#00628b] text-white px-4 py-2 text-sm font-semibold"
            >
              <Plus size={16} /> Set timetable
            </Link>
          )}
          <button
            type="button"
            onClick={exportExcel}
            className="inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 px-3 py-2 text-sm font-medium"
          >
            <Download size={14} /> Export Excel
          </button>
        </div>
      </div>

      <div className="bg-[#f8f9fa] rounded-lg border border-gray-200 p-4 space-y-3">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label className="block text-xs font-semibold text-gray-600 mb-1">Search lecturer</label>
            <input
              value={search.lecturer}
              onChange={(e) => setSearch((p) => ({ ...p, lecturer: e.target.value }))}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
              placeholder="Name / email / phone"
            />
          </div>
          <div>
            <label className="block text-xs font-semibold text-gray-600 mb-1">Search facility</label>
            <input
              value={search.facility}
              onChange={(e) => setSearch((p) => ({ ...p, facility: e.target.value }))}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
              placeholder="Name or code"
            />
          </div>
          <div>
            <label className="block text-xs font-semibold text-gray-600 mb-1">Search module</label>
            <input
              value={search.module}
              onChange={(e) => setSearch((p) => ({ ...p, module: e.target.value }))}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
              placeholder="Name or code"
            />
          </div>
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => setAppliedSearch({ ...search })}
            className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-[#031f50] text-white text-xs font-semibold"
          >
            <Search size={12} /> Search
          </button>
          <button
            type="button"
            onClick={() => {
              setSearch({ lecturer: '', facility: '', module: '' });
              setAppliedSearch({ lecturer: '', facility: '', module: '' });
            }}
            className="px-3 py-1.5 rounded-lg border text-xs"
          >
            Clear search
          </button>
        </div>
      </div>

      <div className="bg-[#f8f9fa] rounded-lg border border-gray-200 p-4">
        <div className="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3">
          <div>
            <label className="block text-sm font-semibold mb-1">Timetable ID</label>
            <input
              type="number"
              value={filters.id}
              onChange={(e) => setFilters((p) => ({ ...p, id: e.target.value }))}
              className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
            />
          </div>
          <div>
            <label className="block text-sm font-semibold mb-1">College</label>
            <select
              value={filters.college}
              onChange={(e) =>
                setFilters((p) => ({ ...p, college: e.target.value, school: '', program: '', group: '' }))
              }
              className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
            >
              <option value="">All</option>
              {collegeOptions.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.text}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-semibold mb-1">School</label>
            <select
              value={filters.school}
              onChange={(e) => setFilters((p) => ({ ...p, school: e.target.value, program: '', group: '' }))}
              className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
            >
              <option value="">All</option>
              {schoolOptions.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.text}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-semibold mb-1">Program</label>
            <select
              value={filters.program}
              onChange={(e) => setFilters((p) => ({ ...p, program: e.target.value, group: '' }))}
              className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
            >
              <option value="">All</option>
              {programOptions.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.text}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-semibold mb-1">Year of study</label>
            <select
              value={filters.yearOfStudy}
              onChange={(e) => setFilters((p) => ({ ...p, yearOfStudy: e.target.value, group: '' }))}
              className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
            >
              <option value="">All</option>
              {[1, 2, 3, 4, 5].map((y) => (
                <option key={y} value={y}>
                  Year {y}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-semibold mb-1">Group</label>
            <select
              value={filters.group}
              onChange={(e) => setFilters((p) => ({ ...p, group: e.target.value }))}
              className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
            >
              <option value="">All</option>
              {groupOptions.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.text}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-semibold mb-1">Campus</label>
            <select
              value={filters.campus}
              onChange={(e) => setFilters((p) => ({ ...p, campus: e.target.value }))}
              className="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
            >
              <option value="">All</option>
              {campusOptions.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.text}
                </option>
              ))}
            </select>
          </div>
        </div>
        <button
          type="button"
          onClick={resetFilters}
          className="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-[#031f50] text-white text-xs font-semibold"
        >
          <RotateCcw size={12} /> Reset filters
        </button>
      </div>

      <div>
        <h2 className="m-0 text-xl font-bold text-[#031f50] border-b border-gray-200 pb-2">
          General timetable
        </h2>
        <div className="mt-2 text-sm text-gray-700 leading-snug">
          {currentView.length === 0 ? (
            <em>Currently viewing full timetable (no filters applied)</em>
          ) : (
            currentView.map((line) => (
              <div key={line}>
                <strong>{line.split(':')[0]}:</strong>
                {line.includes(':') ? line.slice(line.indexOf(':') + 1) : ''}
              </div>
            ))
          )}
        </div>
      </div>

      <div className="bg-white rounded-lg border shadow-sm overflow-hidden">
        {loading ? (
          <div className="py-16 text-center text-gray-500 text-base">Loading timetable…</div>
        ) : filtered.length === 0 ? (
          <div className="py-16 text-center text-gray-500 text-base">
            No teaching plans match these filters.
            {canManage && (
              <>
                {' '}
                <Link to={appPath('set-timetable')} className="text-[#00628b] hover:underline">
                  Set timetable
                </Link>
              </>
            )}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm border-collapse">
              <thead>
                <tr>
                  <th rowSpan={2} className="border px-2.5 py-2 text-white text-sm font-semibold" style={{ background: 'rgb(99,124,167)' }}>
                    ID
                  </th>
                  <th rowSpan={2} className="border px-2.5 py-2 text-white text-sm font-semibold" style={{ background: 'rgb(99,124,167)' }}>
                    Day
                  </th>
                  <th rowSpan={2} className="border px-2.5 py-2 text-white text-sm font-semibold" style={{ background: 'rgb(99,124,167)' }}>
                    Time
                  </th>
                  <th rowSpan={2} className="border px-2.5 py-2 text-white text-sm font-semibold" style={{ background: 'rgb(99,124,167)' }}>
                    Course
                  </th>
                  <th rowSpan={2} className="border px-2.5 py-2 text-white text-sm font-semibold" style={{ background: 'rgb(99,124,167)' }}>
                    Code
                  </th>
                  <th rowSpan={2} className="border px-2.5 py-2 text-white text-sm font-semibold" style={{ background: 'rgb(99,124,167)' }}>
                    Credits
                  </th>
                  <th rowSpan={2} className="border px-2.5 py-2 text-white text-sm font-semibold" style={{ background: 'rgb(99,124,167)' }}>
                    Facility
                  </th>
                  <th colSpan={6} className="border px-2.5 py-2 text-white text-center text-sm font-semibold" style={{ background: 'rgb(99,124,167)' }}>
                    Group details
                  </th>
                  <th rowSpan={2} className="border px-2.5 py-2 text-white text-sm font-semibold" style={{ background: 'rgb(99,124,167)' }}>
                    Lecturers
                  </th>
                </tr>
                <tr>
                  {['Group', 'Year of Study', 'Program', 'School', 'Campus', 'College'].map((h) => (
                    <th key={h} className="border px-2.5 py-2 text-white text-sm font-semibold" style={{ background: 'rgb(99,124,167)' }}>
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {filtered.map((t) => {
                  const sessionText = (t.sessions || [])
                    .map((s) => `${s.day} ${fmtTime(s.start_time)}-${fmtTime(s.end_time)}`)
                    .join(' · ');
                  const first = t.sessions?.[0];
                  const lecturers = formatPlanLecturers(t);
                  const groups = t.groups?.length ? t.groups : [null];
                  return groups.map((g, gi) => (
                    <tr
                      key={`${t.id}-${gi}`}
                      className={`${gi % 2 === 0 ? 'bg-white' : 'bg-[#f8f9fa]'} ${
                        canManage ? 'cursor-pointer hover:bg-sky-50/80' : ''
                      }`}
                      onClick={canManage ? () => setEditingPlan(t) : undefined}
                      title={canManage ? 'Click to view / edit' : undefined}
                    >
                      {gi === 0 && (
                        <>
                          <td rowSpan={groups.length} className="border px-2.5 py-2 align-middle text-center">
                            <span className={canManage ? 'text-[#00628b] font-semibold underline-offset-2 hover:underline' : ''}>
                              {t.id}
                            </span>
                          </td>
                          <td rowSpan={groups.length} className="border px-2.5 py-2 align-middle">
                            {first?.day || '—'}
                            {(t.sessions || []).length > 1 ? (
                              <div className="text-xs text-gray-500 mt-0.5">{sessionText}</div>
                            ) : null}
                          </td>
                          <td rowSpan={groups.length} className="border px-2.5 py-2 align-middle whitespace-nowrap">
                            {first ? `${fmtTime(first.start_time)}-${fmtTime(first.end_time)}` : '—'}
                          </td>
                          <td rowSpan={groups.length} className="border px-2.5 py-2 align-middle text-left">
                            {t.course || '—'}
                          </td>
                          <td rowSpan={groups.length} className="border px-2.5 py-2 align-middle">
                            {t.code || '—'}
                          </td>
                          <td rowSpan={groups.length} className="border px-2.5 py-2 align-middle text-center">
                            {t.credits ?? '—'}
                          </td>
                          <td rowSpan={groups.length} className="border px-2.5 py-2 align-middle">
                            <div className="font-medium">{t.facility?.name || '—'}</div>
                            {t.facility?.buildCode &&
                            String(t.facility.buildCode).trim().toLowerCase() !==
                              String(t.facility.name || '').trim().toLowerCase() ? (
                              <div className="text-xs text-slate-500 mt-0.5">
                                Building: {t.facility.buildCode}
                              </div>
                            ) : null}
                            {t.facility?.buildName &&
                            String(t.facility.buildName).trim().toLowerCase() !==
                              String(t.facility.buildCode || '').trim().toLowerCase() &&
                            String(t.facility.buildName).trim().toLowerCase() !==
                              String(t.facility.name || '').trim().toLowerCase() ? (
                              <div className="text-xs text-slate-400">{t.facility.buildName}</div>
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
                        <td rowSpan={groups.length} className="border px-2.5 py-2 align-middle text-left">
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
        Showing {filtered.length} plan(s) of {rows.length} for current academic period.
        {canManage ? ' Click any row to edit module, facility, lecturers, sessions, or groups.' : ''}
      </p>

      {canManage && (
        <EditTeachingPlanModal
          open={Boolean(editingPlan)}
          plan={editingPlan}
          meta={meta}
          canDelete
          onClose={() => setEditingPlan(null)}
          onSaved={() => load()}
          onDeleted={() => load()}
          showError={showError}
          showSuccess={showSuccess}
          showWarning={showWarning}
        />
      )}
    </div>
  );
}
