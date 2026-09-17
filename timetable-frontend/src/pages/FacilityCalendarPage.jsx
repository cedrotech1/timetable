import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { CalendarDays, ArrowLeft } from 'lucide-react';
import { timetableService } from '../services/api';
import { useNotification } from '../contexts/NotificationContext';
import { appPath } from '../utils/appPaths';
import ModalShell, { ModalPrimaryButton } from '../components/ModalShell';
import { capitalizePersonName } from '../utils/formatDisplay';

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const DAY_START_MIN = 8 * 60;
const DAY_END_MIN = 18 * 60;
const HOUR_PX = 72;

function fmt(t) {
  return String(t || '').slice(0, 5);
}

function toMinutes(t) {
  const s = fmt(t);
  const [h, m] = s.split(':').map(Number);
  if (Number.isNaN(h)) return DAY_START_MIN;
  return h * 60 + (Number.isNaN(m) ? 0 : m);
}

function clamp(n, min, max) {
  return Math.max(min, Math.min(max, n));
}

function SlotDetailModal({ slot, facility, onClose }) {
  if (!slot) return null;
  const groups = slot.groups || [];
  const combined = Boolean(slot.combinedClass) || groups.length > 1;
  const totalStudents = groups.reduce((s, g) => s + (Number(g.size) || 0), 0);

  return (
    <ModalShell
      open
      onClose={onClose}
      size="md"
      accent="amber"
      title="Session details"
      subtitle={`${slot.day} · ${fmt(slot.startTime)}–${fmt(slot.endTime)}`}
      bodyClassName="px-4 sm:px-5 py-4"
      footer={<ModalPrimaryButton onClick={onClose}>Close</ModalPrimaryButton>}
    >
        {combined && (
          <div className="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
            <strong>Combined class (OK)</strong> — {groups.length} groups share this module in one
            facility / one timetable row
            {totalStudents > 0 ? ` · ~${totalStudents} students` : ''}.
          </div>
        )}
        <dl className="space-y-3 text-sm">
          <div>
            <dt className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Module</dt>
            <dd className="m-0 mt-0.5 font-semibold text-[#031f50]">
              {[slot.moduleCode, slot.moduleName].filter(Boolean).join(' — ') || '—'}
            </dd>
            {(slot.moduleCredits != null || slot.moduleYear != null) && (
              <dd className="m-0 text-xs text-slate-500">
                {slot.moduleCredits != null ? `${slot.moduleCredits} credits` : ''}
                {slot.moduleYear != null ? ` · Year ${slot.moduleYear}` : ''}
                {slot.moduleSemester != null ? ` · Sem ${slot.moduleSemester}` : ''}
              </dd>
            )}
          </div>

          {(slot.programName || slot.programCode) && (
            <div>
              <dt className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Program</dt>
              <dd className="m-0 mt-0.5 text-slate-900">
                {[slot.programCode, slot.programName].filter(Boolean).join(' — ')}
              </dd>
            </div>
          )}

          <div>
            <dt className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Facility</dt>
            <dd className="m-0 mt-0.5 text-slate-900">
              {slot.facilityName || facility?.name || '—'}
              {slot.facilityCapacity != null || facility?.capacity != null
                ? ` · Capacity ${slot.facilityCapacity ?? facility.capacity}`
                : ''}
            </dd>
            <dd className="m-0 text-xs text-slate-500">
              {[slot.facilityCampus || facility?.campus?.name, slot.facilityType || facility?.type]
                .filter(Boolean)
                .join(' · ') || null}
            </dd>
          </div>

          <div>
            <dt className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
              Groups {combined ? `(${groups.length} together)` : ''}
            </dt>
            <dd className="m-0 mt-0.5 space-y-2">
              {groups.length === 0 ? (
                <span className="text-slate-500">—</span>
              ) : (
                groups.map((g, i) => (
                  <div key={g.id || i} className="rounded-xl border border-[#e8a05c]/35 bg-[#fff8f2] px-3 py-2">
                    <div className="font-semibold text-[#031f50]">
                      {g.name}
                      {g.size != null ? ` (${g.size})` : ''}
                    </div>
                    <div className="text-xs text-slate-600 mt-0.5">
                      {[g.program, g.yearOfStudy != null ? `Year ${g.yearOfStudy}` : null, g.campus]
                        .filter(Boolean)
                        .join(' · ')}
                    </div>
                    {(g.school || g.college) && (
                      <div className="text-xs text-slate-500">
                        {[g.school, g.college].filter(Boolean).join(' · ')}
                      </div>
                    )}
                  </div>
                ))
              )}
            </dd>
          </div>

          <div>
            <dt className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
              Module leader (ML)
            </dt>
            <dd className="m-0 mt-0.5 text-slate-900">
              {slot.leader?.names ? `${capitalizePersonName(slot.leader.names)} (ML)` : '—'}
              {slot.leader?.urEmail || slot.leader?.email ? (
                <span className="block text-xs text-slate-500">
                  {slot.leader.urEmail || slot.leader.email}
                </span>
              ) : null}
            </dd>
          </div>

          {(slot.otherLecturers || []).length > 0 && (
            <div>
              <dt className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                Other lecturers
              </dt>
              <dd className="m-0 mt-1.5 flex flex-wrap gap-1.5">
                {slot.otherLecturers.map((u) => (
                  <span
                    key={u.id || u.names}
                    className="inline-flex px-2 py-1 rounded-lg text-[11px] font-medium bg-[#fff4eb] text-[#9a4518] border border-[#e8a05c]/50"
                  >
                    {capitalizePersonName(u.names)}
                  </span>
                ))}
              </dd>
            </div>
          )}

          <div className="grid grid-cols-2 gap-3 pt-1">
            <div>
              <dt className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Status</dt>
              <dd className="m-0 mt-0.5 text-slate-900">{slot.status || '—'}</dd>
            </div>
            <div>
              <dt className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Plan ID</dt>
              <dd className="m-0 mt-0.5 text-slate-900">#{slot.timetableId}</dd>
            </div>
          </div>
        </dl>
    </ModalShell>
  );
}

/** Continuous timeline: blocks stretch from exact start → exact end. */
function FacilityWeekGrid({ block, onSlotClick, highlightDay = '' }) {
  const rangeStart = useMemo(() => {
    let min = DAY_START_MIN;
    for (const d of DAYS) {
      for (const s of block.byDay?.[d] || []) {
        min = Math.min(min, toMinutes(s.startTime));
      }
    }
    return Math.floor(min / 60) * 60;
  }, [block.byDay]);

  const rangeEnd = useMemo(() => {
    let max = DAY_END_MIN;
    for (const d of DAYS) {
      for (const s of block.byDay?.[d] || []) {
        max = Math.max(max, toMinutes(s.endTime));
      }
    }
    return Math.ceil(max / 60) * 60;
  }, [block.byDay]);

  const hours = useMemo(() => {
    const list = [];
    for (let m = rangeStart; m < rangeEnd; m += 60) list.push(m);
    return list;
  }, [rangeStart, rangeEnd]);

  const bodyH = ((rangeEnd - rangeStart) / 60) * HOUR_PX;
  const visibleDays = highlightDay ? [highlightDay] : DAYS;

  const layoutForDay = (day) => {
    const sessions = [...(block.byDay?.[day] || [])].sort(
      (a, b) => toMinutes(a.startTime) - toMinutes(b.startTime)
    );
    const columns = [];
    const placed = sessions.map((s) => {
      const start = clamp(toMinutes(s.startTime), rangeStart, rangeEnd);
      let end = clamp(toMinutes(s.endTime), rangeStart, rangeEnd);
      if (end <= start) end = start + 30;
      const top = ((start - rangeStart) / 60) * HOUR_PX;
      const height = ((end - start) / 60) * HOUR_PX;

      let col = 0;
      while (columns[col]?.some((o) => !(end <= o.start || start >= o.end))) col += 1;
      if (!columns[col]) columns[col] = [];
      columns[col].push({ start, end });

      return { session: s, top, height, col, start, end };
    });
    const maxCol = Math.max(1, ...placed.map((x) => x.col + 1));
    return placed.map((item) => ({ ...item, maxCol }));
  };

  const colCount = visibleDays.length;
  const gridCols = `72px repeat(${colCount}, 1fr)`;

  return (
    <div className="overflow-x-auto p-3">
      <div className="min-w-[720px]">
        <div className="grid gap-1 mb-1" style={{ gridTemplateColumns: gridCols }}>
          <div className="bg-gray-100 rounded-md px-2 py-2 text-xs font-semibold text-gray-600">Time</div>
          {visibleDays.map((d) => (
            <div
              key={d}
              className="bg-[#00628b] text-white rounded-md px-2 py-2 text-xs font-semibold text-center"
            >
              {d}
            </div>
          ))}
        </div>

        <div className="grid gap-1" style={{ gridTemplateColumns: gridCols }}>
          <div className="relative border-t border-gray-100" style={{ height: bodyH }}>
            {hours.map((m) => {
              const top = ((m - rangeStart) / 60) * HOUR_PX;
              const label = `${String(Math.floor(m / 60)).padStart(2, '0')}:00`;
              return (
                <div
                  key={m}
                  className="absolute left-0 right-0 text-xs font-medium text-gray-600 px-1 border-t border-gray-100"
                  style={{ top, height: HOUR_PX }}
                >
                  <span className="relative -top-2 bg-white pr-1">{label}</span>
                </div>
              );
            })}
          </div>

          {visibleDays.map((day) => {
            const laid = layoutForDay(day);
            return (
              <div
                key={day}
                className="relative rounded-md border border-gray-100 bg-[#fafbfc] overflow-hidden"
                style={{ height: bodyH }}
              >
                {hours.map((m) => {
                  const top = ((m - rangeStart) / 60) * HOUR_PX;
                  return (
                    <div
                      key={m}
                      className="absolute left-0 right-0 border-t border-gray-100 pointer-events-none"
                      style={{ top, height: HOUR_PX }}
                    />
                  );
                })}

                {laid.map(({ session: s, top, height, col, maxCol }) => {
                  const widthPct = 100 / maxCol;
                  const leftPct = col * widthPct;
                  return (
                    <button
                      key={`${s.timetableId}-${s.startTime}-${s.endTime}-${col}`}
                      type="button"
                      onClick={() => onSlotClick(s, block.facility)}
                      className="absolute z-10 text-left rounded-md bg-[#012a70] text-white px-2 py-1.5 text-[10px] leading-snug hover:bg-[#00628b] transition-colors cursor-pointer overflow-hidden shadow-sm border border-white/15"
                      style={{
                        top: top + 1,
                        height: Math.max(height - 2, 24),
                        left: `calc(${leftPct}% + 2px)`,
                        width: `calc(${widthPct}% - 4px)`,
                      }}
                      title={`${fmt(s.startTime)}–${fmt(s.endTime)}${(s.groups || []).length > 1 ? ` · Combined ${(s.groups || []).length} groups` : ''}`}
                    >
                      <div className="font-semibold opacity-95">
                        {fmt(s.startTime)}–{fmt(s.endTime)}
                        {(s.groups || []).length > 1 ? (
                          <span className="ml-1 inline-block rounded bg-emerald-400/90 text-[#012a70] px-1 text-[9px] font-bold">
                            {s.groups.length} groups
                          </span>
                        ) : null}
                      </div>
                      <div className="font-semibold line-clamp-2">
                        {s.moduleCode ? `${s.moduleCode} ` : ''}
                        {s.moduleName}
                      </div>
                      {height > 56 && (
                        <div className="opacity-90 mt-0.5 line-clamp-2">
                          {(s.groups || []).map((g) => g.name).filter(Boolean).join(', ') || '—'}
                        </div>
                      )}
                      {height > 88 && (
                        <div className="opacity-80 truncate">
                          {(s.groups || [])[0]?.program || s.programName || ''}
                        </div>
                      )}
                    </button>
                  );
                })}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

const TIME_OPTIONS = [
  '08:00',
  '09:00',
  '10:00',
  '11:00',
  '12:00',
  '13:00',
  '14:00',
  '15:00',
  '16:00',
  '17:00',
  '18:00',
];

function sessionsOverlapWindow(sessions, day, fromMin, toMin) {
  return (sessions || []).some((s) => {
    if (day && String(s.day) !== String(day)) return false;
    const a = toMinutes(s.startTime);
    const b = toMinutes(s.endTime);
    return a < toMin && b > fromMin;
  });
}

function FilterSelect({ label, value, onChange, children, className = '' }) {
  return (
    <label className={`flex flex-col gap-0.5 min-w-0 ${className}`}>
      <span className="text-[10px] font-medium uppercase tracking-wide text-gray-500">{label}</span>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-800"
      >
        {children}
      </select>
    </label>
  );
}

export default function FacilityCalendarPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { showError } = useNotification();
  const [loading, setLoading] = useState(true);
  const [calendar, setCalendar] = useState({ days: DAYS, facilities: [] });
  const [meta, setMeta] = useState(null);
  const [selectedId, setSelectedId] = useState(id || '');
  const [detail, setDetail] = useState(null);
  const [blinkId, setBlinkId] = useState(null);

  const [academicYearId, setAcademicYearId] = useState('');
  const [semester, setSemester] = useState('');
  const [campusId, setCampusId] = useState('');
  const [occupancy, setOccupancy] = useState('all'); // all | booked | fully_free | free_on_day | free_at_time
  const [filterDay, setFilterDay] = useState('');
  const [timeFrom, setTimeFrom] = useState('08:00');
  const [timeTo, setTimeTo] = useState('13:00');
  const [search, setSearch] = useState('');
  const [minCapacity, setMinCapacity] = useState('');
  const [facilityType, setFacilityType] = useState('');
  const [filtersReady, setFiltersReady] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const params = {};
      if (id) params.facilityId = id;
      if (academicYearId) params.academicYearId = academicYearId;
      if (semester) params.semester = semester;
      if (campusId) params.campusId = campusId;

      const calRes = await timetableService.facilityCalendar(params);
      const data = calRes?.data || { days: DAYS, facilities: [] };
      const m = calRes?.meta || null;
      setCalendar(data);
      setMeta(m);

      if (!filtersReady && m) {
        setAcademicYearId(String(m.academicYearId || m.liveAcademicYearId || ''));
        setSemester(String(m.semester || m.liveSemester || ''));
        setFiltersReady(true);
      }
      if (id) setSelectedId(String(id));
    } catch (error) {
      showError(error.response?.data?.message || 'Failed to load facility calendar');
    } finally {
      setLoading(false);
    }
  }, [id, academicYearId, semester, campusId, showError, filtersReady]);

  useEffect(() => {
    load();
  }, [load]);

  const academicYears = meta?.academicYears || [];
  const campuses = meta?.campuses || [];

  const facilityTypes = useMemo(() => {
    const set = new Set();
    for (const b of calendar.facilities || []) {
      if (b.facility?.type) set.add(b.facility.type);
    }
    return [...set].sort();
  }, [calendar.facilities]);

  const filteredBlocks = useMemo(() => {
    let list = [...(calendar.facilities || [])];
    const q = search.trim().toLowerCase();
    const minCap = minCapacity ? Number(minCapacity) : 0;
    const fromMin = toMinutes(timeFrom);
    const toMin = toMinutes(timeTo);

    list = list.filter((b) => {
      const f = b.facility || {};
      const sessions = b.sessions || [];
      if (q) {
        const hay = `${f.name || ''} ${f.buildName || ''} ${f.campus?.name || ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      if (minCap && Number(f.capacity || 0) < minCap) return false;
      if (facilityType && String(f.type || '') !== facilityType) return false;

      const bookedAny = sessions.length > 0;
      const bookedOnDay = filterDay
        ? sessions.some((s) => String(s.day) === filterDay)
        : bookedAny;
      const busyAtTime = sessionsOverlapWindow(sessions, filterDay || null, fromMin, toMin);
      // free at time: if day set, only that day; else free on that window any day? Prefer requiring day for free_at_time
      const freeAtTime = !busyAtTime;
      const freeOnDay = filterDay
        ? !sessions.some((s) => String(s.day) === filterDay)
        : !bookedAny;

      if (occupancy === 'booked') {
        if (filterDay) return bookedOnDay;
        if (timeFrom && timeTo && (timeFrom !== '08:00' || timeTo !== '13:00') && filterDay) {
          return busyAtTime;
        }
        return bookedAny;
      }
      if (occupancy === 'fully_free') return !bookedAny;
      if (occupancy === 'free_on_day') return freeOnDay;
      if (occupancy === 'free_at_time') {
        if (!filterDay) return freeAtTime; // free for window on every checked day → treat as no overlap that window on any day when no day: free for that clock range every day? Simpler: no overlap in window across week if no day
        return freeAtTime;
      }
      if (occupancy === 'booked_at_time') return busyAtTime;
      return true;
    });

    // Booked first when browsing all
    list.sort((a, b) => {
      const as = (a.sessions || []).length;
      const bs = (b.sessions || []).length;
      if (as > 0 && bs === 0) return -1;
      if (as === 0 && bs > 0) return 1;
      return String(a.facility?.name || '').localeCompare(String(b.facility?.name || ''));
    });

    if (selectedId) {
      return list.filter((f) => String(f.facility?.id) === String(selectedId));
    }
    return list;
  }, [
    calendar.facilities,
    search,
    minCapacity,
    facilityType,
    occupancy,
    filterDay,
    timeFrom,
    timeTo,
    selectedId,
  ]);

  const firstBookedId = useMemo(() => {
    const hit = filteredBlocks.find((b) => (b.sessions || []).length > 0);
    return hit?.facility?.id != null ? String(hit.facility.id) : null;
  }, [filteredBlocks]);

  useEffect(() => {
    if (loading || !firstBookedId || selectedId) return;
    setBlinkId(firstBookedId);
    const el = document.getElementById(`facility-cal-${firstBookedId}`);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    const t = setTimeout(() => setBlinkId(null), 4500);
    return () => clearTimeout(t);
  }, [loading, firstBookedId, selectedId, academicYearId, semester, campusId, occupancy]);

  const onPick = (fid) => {
    setSelectedId(fid ? String(fid) : '');
    if (fid) navigate(appPath(`facilities/${fid}/calendar`));
    else navigate(appPath('facilities/calendar'));
  };

  const resetFilters = () => {
    setOccupancy('all');
    setFilterDay('');
    setTimeFrom('08:00');
    setTimeTo('13:00');
    setSearch('');
    setMinCapacity('');
    setFacilityType('');
    setCampusId('');
    if (meta?.liveAcademicYearId) setAcademicYearId(String(meta.liveAcademicYearId));
    if (meta?.liveSemester != null) setSemester(String(meta.liveSemester));
  };

  const totals = useMemo(() => {
    const total = filteredBlocks.length;
    const used = filteredBlocks.filter((b) => (b.sessions || []).length > 0).length;
    return { total, used, free: total - used };
  }, [filteredBlocks]);

  const needsDayHint =
    (occupancy === 'free_on_day' || occupancy === 'free_at_time' || occupancy === 'booked_at_time') &&
    !filterDay;

  return (
    <div className="max-w-[95rem] mx-auto space-y-3">
      <div className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
        <div>
          <Link
            to={appPath('facilities')}
            className="inline-flex items-center gap-1 text-sm text-[#00628b] hover:underline mb-2"
          >
            <ArrowLeft size={14} /> Facilities
          </Link>
          <h1 className="text-2xl font-bold text-[#031f50] m-0 flex items-center gap-2">
            <CalendarDays size={22} className="text-[#00628b]" />
            Facility calendar
          </h1>
          <p className="mt-1 mb-0 text-sm text-gray-500">
            Filter rooms by campus, day, free/booked time, capacity. Click a slot for details.
          </p>
        </div>
      </div>

      {/* Compact filters */}
      <section className="bg-white rounded-xl border shadow-sm p-3 space-y-2">
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 xl:grid-cols-8 gap-2">
          <FilterSelect label="Academic year" value={academicYearId} onChange={setAcademicYearId}>
            {academicYears.map((y) => (
              <option key={y.id} value={y.id}>
                {y.yearLabel || `AY #${y.id}`}
              </option>
            ))}
          </FilterSelect>
          <FilterSelect label="Semester" value={semester} onChange={setSemester}>
            <option value="1">Semester 1</option>
            <option value="2">Semester 2</option>
            <option value="3">Semester 3</option>
          </FilterSelect>
          <FilterSelect label="Campus" value={campusId} onChange={setCampusId}>
            <option value="">All campuses</option>
            {campuses.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </FilterSelect>
          <FilterSelect label="Availability" value={occupancy} onChange={setOccupancy}>
            <option value="all">All facilities</option>
            <option value="booked">Booked (has slots)</option>
            <option value="fully_free">Fully free (no slots)</option>
            <option value="free_on_day">Free on day</option>
            <option value="free_at_time">Free at exact time</option>
            <option value="booked_at_time">Booked at exact time</option>
          </FilterSelect>
          <FilterSelect label="Day" value={filterDay} onChange={setFilterDay}>
            <option value="">Any day</option>
            {DAYS.map((d) => (
              <option key={d} value={d}>
                {d}
              </option>
            ))}
          </FilterSelect>
          <FilterSelect label="From" value={timeFrom} onChange={setTimeFrom}>
            {TIME_OPTIONS.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </FilterSelect>
          <FilterSelect label="To" value={timeTo} onChange={setTimeTo}>
            {TIME_OPTIONS.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </FilterSelect>
          <FilterSelect label="Type" value={facilityType} onChange={setFacilityType}>
            <option value="">All types</option>
            {facilityTypes.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </FilterSelect>
        </div>

        <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2 items-end">
          <label className="flex flex-col gap-0.5 min-w-0 sm:col-span-2">
            <span className="text-[10px] font-medium uppercase tracking-wide text-gray-500">Search</span>
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Name / building…"
              className="rounded-md border border-gray-200 px-2 py-1.5 text-xs"
            />
          </label>
          <label className="flex flex-col gap-0.5 min-w-0">
            <span className="text-[10px] font-medium uppercase tracking-wide text-gray-500">
              Min capacity
            </span>
            <input
              type="number"
              min="0"
              value={minCapacity}
              onChange={(e) => setMinCapacity(e.target.value)}
              placeholder="e.g. 100"
              className="rounded-md border border-gray-200 px-2 py-1.5 text-xs"
            />
          </label>
          <FilterSelect
            label="Facility"
            value={selectedId}
            onChange={onPick}
            className="sm:col-span-2"
          >
            <option value="">All matching ({filteredBlocks.length})</option>
            {(calendar.facilities || []).map((b) => (
              <option key={b.facility.id} value={b.facility.id}>
                {b.facility.name}
                {(b.sessions || []).length ? ` · ${b.sessions.length} slots` : ' · free'}
              </option>
            ))}
          </FilterSelect>
          <button
            type="button"
            onClick={resetFilters}
            className="rounded-md border border-gray-200 px-2 py-1.5 text-xs text-gray-600 hover:bg-gray-50"
          >
            Reset filters
          </button>
        </div>

        {needsDayHint && (
          <p className="m-0 text-[11px] text-amber-700">
            Tip: pick a <strong>Day</strong> for free/booked-at-time filters (time From–To is used for the window).
          </p>
        )}
      </section>

      {!loading && (
        <div className="flex flex-wrap gap-2 text-xs text-gray-600">
          <span className="rounded-lg border bg-white px-2.5 py-1">
            Showing <strong>{totals.total}</strong>
          </span>
          <span className="rounded-lg border bg-white px-2.5 py-1">
            Booked: <strong>{totals.used}</strong>
          </span>
          <span className="rounded-lg border bg-white px-2.5 py-1">
            Free: <strong>{totals.free}</strong>
          </span>
          {firstBookedId && !selectedId && (
            <span className="rounded-lg border border-[#00628b]/40 bg-[#e8f4f8] px-2.5 py-1 text-[#00628b]">
              First with slots is highlighted
            </span>
          )}
        </div>
      )}

      {loading ? (
        <div className="py-16 text-center text-gray-500">Loading calendar…</div>
      ) : filteredBlocks.length === 0 ? (
        <div className="bg-white rounded-xl border p-10 text-center text-gray-500 text-sm">
          No facilities match these filters.
        </div>
      ) : (
        filteredBlocks.map((block) => {
          const fid = String(block.facility.id);
          const isBlink = blinkId === fid;
          return (
            <div
              key={fid}
              id={`facility-cal-${fid}`}
              className={`bg-white rounded-xl border shadow-sm overflow-hidden ${isBlink ? 'facility-blink' : ''}`}
            >
              <div className="bg-[#012a70] text-white px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                  <h2 className="m-0 text-lg font-semibold">{block.facility.name}</h2>
                  <p className="m-0 mt-1 text-sm opacity-90">
                    {block.facility.campus?.name || '—'}
                    {block.facility.capacity != null ? ` · Capacity ${block.facility.capacity}` : ''}
                    {block.facility.type ? ` · ${block.facility.type}` : ''}
                    {block.facility.buildName ? ` · ${block.facility.buildName}` : ''}
                  </p>
                </div>
                <div className="text-sm flex items-center gap-3">
                  <span>
                    {block.stats?.sessions || 0} session(s) · {block.stats?.daysUsed || 0} day(s)
                    {(block.stats?.sessions || 0) === 0 ? ' · entirely free' : ''}
                  </span>
                  {!selectedId && (
                    <button type="button" className="underline" onClick={() => onPick(block.facility.id)}>
                      Focus
                    </button>
                  )}
                </div>
              </div>

              <FacilityWeekGrid
                block={block}
                highlightDay={filterDay}
                onSlotClick={(slot, facility) => setDetail({ slot, facility })}
              />
            </div>
          );
        })
      )}

      {detail && (
        <SlotDetailModal
          slot={detail.slot}
          facility={detail.facility}
          onClose={() => setDetail(null)}
        />
      )}
    </div>
  );
}
