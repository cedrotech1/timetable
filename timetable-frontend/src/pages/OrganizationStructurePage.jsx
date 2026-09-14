import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Plus,
  Pencil,
  Trash2,
  CheckCircle2,
  Building2,
  Landmark,
  School,
  BookOpen,
  RefreshCw,
  Users,
  Search,
  GraduationCap,
  Layers,
} from 'lucide-react';
import {
  organizationService,
  campusesService,
  collegesService,
  schoolsService,
  programsService,
  modulesService,
} from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import { useNotification } from '../contexts/NotificationContext';
import ModalShell, { ModalPrimaryButton, ModalSecondaryButton } from '../components/ModalShell';
import { canManageOrg } from '../utils/roles';
import { appPath } from '../utils/appPaths';

const ENTITY = {
  campus: 'campus',
  college: 'college',
  school: 'school',
  program: 'program',
};

function EntityModal({ open, title, fields, form, setForm, onClose, onSave, saving }) {
  return (
    <ModalShell
      open={open}
      onClose={onClose}
      size="md"
      title={title}
      subtitle="Fill in the fields below, then save"
      bodyClassName="px-4 sm:px-5 py-4"
      footer={
        <>
          <ModalSecondaryButton onClick={onClose}>Cancel</ModalSecondaryButton>
          <ModalPrimaryButton type="submit" form="org-entity-form" disabled={saving}>
            {saving ? 'Saving…' : 'Save'}
          </ModalPrimaryButton>
        </>
      }
    >
      <form id="org-entity-form" onSubmit={onSave} className="space-y-4">
        {fields.map((field) => (
          <div key={field.name}>
            <label className="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">
              {field.label}
            </label>
            {field.type === 'select' ? (
              <select
                required={field.required}
                value={form[field.name] ?? ''}
                onChange={(e) =>
                  setForm((prev) => ({
                    ...prev,
                    [field.name]: field.valueAsNumber ? Number(e.target.value) : e.target.value,
                  }))
                }
                className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25"
              >
                <option value="">Select…</option>
                {(field.options || []).map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            ) : (
              <input
                type={field.type || 'text'}
                required={field.required}
                value={form[field.name] ?? ''}
                onChange={(e) => setForm((prev) => ({ ...prev, [field.name]: e.target.value }))}
                className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25"
                placeholder={field.placeholder}
              />
            )}
          </div>
        ))}
      </form>
    </ModalShell>
  );
}

/** Group modules: Year → Semester → list */
function groupModulesByYearSemester(modules = []) {
  const byYear = new Map();
  for (const m of modules) {
    const year = Number(m.year) || 0;
    const sem = String(m.semester ?? '1');
    if (!byYear.has(year)) byYear.set(year, new Map());
    const bySem = byYear.get(year);
    if (!bySem.has(sem)) bySem.set(sem, []);
    bySem.get(sem).push(m);
  }
  const years = [...byYear.keys()].sort((a, b) => a - b);
  return years.map((year) => {
    const bySem = byYear.get(year);
    const semesters = [...bySem.keys()].sort((a, b) => Number(a) - Number(b));
    return {
      year,
      total: semesters.reduce((n, s) => n + bySem.get(s).length, 0),
      semesters: semesters.map((sem) => ({
        semester: sem,
        modules: [...bySem.get(sem)].sort((a, b) =>
          String(a.code || a.name || '').localeCompare(String(b.code || b.name || ''))
        ),
      })),
    };
  });
}

function ModulesBrowserModal({ open, program, modules, loading, onClose, canManage }) {
  const [q, setQ] = useState('');
  const [yearFilter, setYearFilter] = useState('all');
  const [semFilter, setSemFilter] = useState('all');

  useEffect(() => {
    if (!open) return;
    setQ('');
    setYearFilter('all');
    setSemFilter('all');
  }, [open, program?.id]);

  const filtered = useMemo(() => {
    const query = q.trim().toLowerCase();
    return (modules || []).filter((m) => {
      if (yearFilter !== 'all' && String(m.year) !== String(yearFilter)) return false;
      if (semFilter !== 'all' && String(m.semester) !== String(semFilter)) return false;
      if (!query) return true;
      const hay = `${m.code || ''} ${m.name || ''}`.toLowerCase();
      return hay.includes(query);
    });
  }, [modules, q, yearFilter, semFilter]);

  const grouped = useMemo(() => groupModulesByYearSemester(filtered), [filtered]);

  const yearOptions = useMemo(() => {
    const set = new Set((modules || []).map((m) => Number(m.year) || 0));
    return [...set].filter((y) => y > 0).sort((a, b) => a - b);
  }, [modules]);

  const semOptions = useMemo(() => {
    const set = new Set((modules || []).map((m) => String(m.semester ?? '1')));
    return [...set].sort((a, b) => Number(a) - Number(b));
  }, [modules]);

  return (
    <ModalShell
      open={open}
      onClose={onClose}
      size="xl"
      accent="brand"
      title={
        <div className="flex items-center gap-2.5 min-w-0">
          <span className="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-[#031f50]/5 text-[#00628b] border border-[#00628b]/15 shrink-0">
            <BookOpen size={18} />
          </span>
          <span className="min-w-0">
            <span className="block text-base sm:text-lg font-semibold text-[#031f50] tracking-tight truncate">
              Modules by year & semester
            </span>
            <span className="block text-xs text-slate-500 mt-0.5 truncate">
              {program?.name || 'Program'}
              {program?.code ? ` · ${program.code}` : ''}
              {modules?.length != null ? ` · ${modules.length} total` : ''}
            </span>
          </span>
        </div>
      }
      footer={
        <>
          <ModalSecondaryButton onClick={onClose}>Close</ModalSecondaryButton>
          {canManage && program?.id ? (
            <Link
              to={`${appPath('modules')}?programId=${program.id}`}
              className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#00628b] text-white text-sm font-semibold hover:bg-[#004f70]"
              onClick={onClose}
            >
              <Layers size={14} /> Manage modules
            </Link>
          ) : null}
        </>
      }
      bodyClassName="flex flex-col !overflow-hidden"
    >
      {/* Filters */}
      <div className="px-4 sm:px-5 py-3 border-b border-slate-100 bg-white shrink-0 space-y-2.5">
        <div className="relative">
          <Search size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#00628b]/70" />
          <input
            type="search"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Search module code or name…"
            className="w-full rounded-xl border border-slate-200 bg-[#f8fafc] pl-10 pr-3 py-2.5 text-sm focus:outline-none focus:bg-white focus:border-[#00628b] focus:ring-2 focus:ring-[#00628b]/20"
          />
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-[10px] uppercase tracking-wider text-slate-400 font-semibold mr-1">Year</span>
          <button
            type="button"
            onClick={() => setYearFilter('all')}
            className={`px-2.5 py-1 rounded-full text-[11px] font-semibold border transition ${
              yearFilter === 'all'
                ? 'bg-[#031f50] text-white border-[#031f50]'
                : 'bg-white text-slate-600 border-slate-200 hover:border-[#00628b]/40'
            }`}
          >
            All
          </button>
          {yearOptions.map((y) => (
            <button
              key={y}
              type="button"
              onClick={() => setYearFilter(String(y))}
              className={`px-2.5 py-1 rounded-full text-[11px] font-semibold border transition ${
                String(yearFilter) === String(y)
                  ? 'bg-[#c45c26] text-white border-[#c45c26]'
                  : 'bg-white text-slate-600 border-slate-200 hover:border-[#c45c26]/40'
              }`}
            >
              Year {y}
            </button>
          ))}
          <span className="text-[10px] uppercase tracking-wider text-slate-400 font-semibold ml-2 mr-1">
            Sem
          </span>
          <button
            type="button"
            onClick={() => setSemFilter('all')}
            className={`px-2.5 py-1 rounded-full text-[11px] font-semibold border transition ${
              semFilter === 'all'
                ? 'bg-[#031f50] text-white border-[#031f50]'
                : 'bg-white text-slate-600 border-slate-200'
            }`}
          >
            All
          </button>
          {semOptions.map((s) => (
            <button
              key={s}
              type="button"
              onClick={() => setSemFilter(String(s))}
              className={`px-2.5 py-1 rounded-full text-[11px] font-semibold border transition ${
                String(semFilter) === String(s)
                  ? 'bg-[#00628b] text-white border-[#00628b]'
                  : 'bg-white text-slate-600 border-slate-200'
              }`}
            >
              Sem {s}
            </button>
          ))}
          <span className="ml-auto text-[11px] text-slate-500 font-medium">
            {filtered.length} shown
          </span>
        </div>
      </div>

      {/* Grouped list */}
      <div className="flex-1 overflow-y-auto px-4 sm:px-5 py-4 bg-[#fbfcfe] min-h-[16rem] space-y-4">
        {loading ? (
          <p className="text-sm text-slate-500 text-center py-10">Loading modules…</p>
        ) : grouped.length === 0 ? (
          <div className="text-center py-12">
            <div className="mx-auto w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
              <BookOpen size={22} />
            </div>
            <p className="m-0 text-sm font-medium text-slate-600">
              {modules?.length ? 'No modules match these filters' : 'No modules for this program yet'}
            </p>
            {canManage && program?.id ? (
              <Link
                to={`${appPath('modules')}?programId=${program.id}`}
                className="inline-block mt-3 text-sm text-[#00628b] font-semibold underline"
                onClick={onClose}
              >
                Add modules
              </Link>
            ) : null}
          </div>
        ) : (
          grouped.map((yearBlock) => (
            <section
              key={yearBlock.year}
              className="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm"
            >
              <header className="flex items-center justify-between gap-2 px-4 py-3 bg-gradient-to-r from-[#031f50] to-[#00628b] text-white">
                <div className="flex items-center gap-2">
                  <GraduationCap size={16} className="opacity-90" />
                  <h4 className="m-0 text-sm font-semibold tracking-tight">
                    {yearBlock.year > 0 ? `Year ${yearBlock.year}` : 'Year not set'}
                  </h4>
                </div>
                <span className="text-[11px] font-medium bg-white/15 px-2 py-0.5 rounded-full">
                  {yearBlock.total} module{yearBlock.total === 1 ? '' : 's'}
                </span>
              </header>

              <div className="divide-y divide-slate-100">
                {yearBlock.semesters.map((semBlock) => (
                  <div key={`${yearBlock.year}-${semBlock.semester}`} className="p-3 sm:p-4">
                    <div className="flex items-center gap-2 mb-2.5">
                      <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-[#fff4eb] text-[#c45c26] border border-[#e8a05c]/50">
                        Semester {semBlock.semester}
                      </span>
                      <span className="text-[11px] text-slate-400">
                        {semBlock.modules.length} module{semBlock.modules.length === 1 ? '' : 's'}
                      </span>
                    </div>
                    <ul className="m-0 p-0 list-none grid grid-cols-1 sm:grid-cols-2 gap-2">
                      {semBlock.modules.map((m) => (
                        <li
                          key={m.id}
                          className="rounded-xl border border-slate-100 bg-[#f8fafc] hover:bg-[#fff8f2] hover:border-[#e8a05c]/40 px-3 py-2.5 transition"
                        >
                          <p className="m-0 text-sm font-semibold text-[#031f50] leading-snug">
                            {m.name}
                          </p>
                          <p className="m-0 mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                            {m.code ? (
                              <span className="font-mono font-semibold text-[#00628b] bg-[#e8f4f8] px-1.5 py-0.5 rounded">
                                {m.code}
                              </span>
                            ) : (
                              <span className="text-slate-400">No code</span>
                            )}
                            <span>·</span>
                            <span>{m.credits ?? 0} credits</span>
                          </p>
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </div>
            </section>
          ))
        )}
      </div>
    </ModalShell>
  );
}

export default function OrganizationStructurePage() {
  const { user } = useAuth();
  const { showSuccess, showError } = useNotification();
  const canManage = canManageOrg(user?.role);

  const [data, setData] = useState({ campuses: [], colleges: [], counts: {} });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [modal, setModal] = useState({ open: false, entity: null, editing: null });
  const [form, setForm] = useState({});
  const [deleteTarget, setDeleteTarget] = useState(null);

  const [modulesDrawer, setModulesDrawer] = useState({ open: false, program: null, modules: [], loading: false });

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const response = await organizationService.getStructure();
      if (response?.success) {
        setData(response.data);
      } else {
        showError(response?.message || 'Failed to load organization structure');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Failed to load organization structure');
    } finally {
      setLoading(false);
    }
  }, [showError]);

  useEffect(() => {
    load();
  }, [load]);

  const flatSchools = useMemo(
    () => (data.colleges || []).flatMap((c) => (c.schools || []).map((s) => ({ ...s, collegeName: c.name }))),
    [data.colleges]
  );

  const openCreate = (entity, defaults = {}) => {
    setModal({ open: true, entity, editing: null });
    setForm(defaults);
  };

  const openEdit = (entity, row) => {
    setModal({ open: true, entity, editing: row });
    if (entity === ENTITY.campus) setForm({ name: row.name });
    if (entity === ENTITY.college) setForm({ name: row.name, fullName: row.fullName });
    if (entity === ENTITY.school) setForm({ name: row.name, collegeId: row.collegeId });
    if (entity === ENTITY.program) setForm({ name: row.name, code: row.code || '', schoolId: row.schoolId });
  };

  const modalFields = useMemo(() => {
    switch (modal.entity) {
      case ENTITY.campus:
        return [{ name: 'name', label: 'Campus name', required: true }];
      case ENTITY.college:
        return [
          { name: 'name', label: 'Short code (e.g. CASS)', required: true },
          { name: 'fullName', label: 'Full name', required: true },
        ];
      case ENTITY.school:
        return [
          { name: 'name', label: 'School / center name', required: true },
          {
            name: 'collegeId',
            label: 'College',
            type: 'select',
            required: true,
            valueAsNumber: true,
            options: (data.colleges || []).map((c) => ({ value: c.id, label: `${c.name} — ${c.fullName}` })),
          },
        ];
      case ENTITY.program:
        return [
          { name: 'name', label: 'Program name', required: true },
          { name: 'code', label: 'Code (optional)' },
          {
            name: 'schoolId',
            label: 'School',
            type: 'select',
            required: true,
            valueAsNumber: true,
            options: flatSchools.map((s) => ({ value: s.id, label: `${s.name} (${s.collegeName})` })),
          },
        ];
      default:
        return [];
    }
  }, [modal.entity, data.colleges, flatSchools]);

  const serviceFor = (entity) => {
    if (entity === ENTITY.campus) return campusesService;
    if (entity === ENTITY.college) return collegesService;
    if (entity === ENTITY.school) return schoolsService;
    if (entity === ENTITY.program) return programsService;
    return null;
  };

  const handleSave = async (e) => {
    e.preventDefault();
    const service = serviceFor(modal.entity);
    if (!service) return;
    try {
      setSaving(true);
      let payload = { ...form };
      if (modal.entity === ENTITY.program) {
        payload = {
          name: form.name,
          code: form.code || null,
          schoolId: Number(form.schoolId),
        };
      }
      if (modal.entity === ENTITY.school) {
        payload = { name: form.name, collegeId: Number(form.collegeId) };
      }

      const response = modal.editing
        ? await service.update(modal.editing.id, payload)
        : await service.create(payload);

      if (response?.success) {
        showSuccess(modal.editing ? 'Updated successfully' : 'Created successfully');
        setModal({ open: false, entity: null, editing: null });
        await load();
      } else {
        showError(response?.message || 'Save failed');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    const service = serviceFor(deleteTarget.entity);
    try {
      setSaving(true);
      const response = await service.remove(deleteTarget.row.id);
      if (response?.success) {
        showSuccess('Deleted successfully');
        setDeleteTarget(null);
        await load();
      } else {
        showError(response?.message || 'Delete failed');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Delete failed');
    } finally {
      setSaving(false);
    }
  };

  const openModules = async (program) => {
    setModulesDrawer({ open: true, program, modules: [], loading: true });
    try {
      const response = await modulesService.getAll({ programId: program.id });
      setModulesDrawer({
        open: true,
        program,
        modules: response?.success ? response.data || [] : [],
        loading: false,
      });
    } catch {
      setModulesDrawer({ open: true, program, modules: [], loading: false });
    }
  };

  const structureRows = useMemo(() => {
    const rows = [];
    for (const college of data.colleges || []) {
      const schools = college.schools || [];
      if (!schools.length) {
        rows.push({
          key: `c-${college.id}`,
          college,
          school: null,
          program: null,
          collegeRowspan: 1,
          schoolRowspan: 1,
          showCollege: true,
          showSchool: true,
        });
        continue;
      }

      let collegeRowspan = 0;
      for (const school of schools) {
        const programCount = (school.programs || []).length;
        collegeRowspan += programCount > 0 ? programCount : 1;
      }

      let collegeShown = false;
      for (const school of schools) {
        const programs = school.programs || [];
        const schoolRowspan = programs.length > 0 ? programs.length : 1;
        let schoolShown = false;

        if (!programs.length) {
          rows.push({
            key: `s-${school.id}`,
            college,
            school,
            program: null,
            collegeRowspan,
            schoolRowspan,
            showCollege: !collegeShown,
            showSchool: true,
          });
          collegeShown = true;
          continue;
        }

        for (const program of programs) {
          rows.push({
            key: `p-${program.id}`,
            college,
            school,
            program,
            collegeRowspan,
            schoolRowspan,
            showCollege: !collegeShown,
            showSchool: !schoolShown,
          });
          collegeShown = true;
          schoolShown = true;
        }
      }
    }
    return rows;
  }, [data.colleges]);

  const counts = data.counts || {};

  return (
    <div className="max-w-7xl mx-auto">
      <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-[#031f50] m-0 border-b-2 border-[#031f50] pb-2 inline-block">
            Institutional Structure
          </h1>
          <p className="mt-2 mb-0 text-sm text-gray-500">
            College → School / center → Programs → Year intakes &amp; groups.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={load}
            className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 text-sm text-gray-700 hover:bg-gray-50"
          >
            <RefreshCw size={16} />
            Refresh
          </button>
          {canManage && (
            <>
              <button
                type="button"
                onClick={() => openCreate(ENTITY.college)}
                className="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-[#00628b] text-white text-sm"
              >
                <Plus size={16} /> College
              </button>
              <button
                type="button"
                onClick={() => openCreate(ENTITY.school)}
                className="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-[#00628b] text-white text-sm"
              >
                <Plus size={16} /> School
              </button>
              <button
                type="button"
                onClick={() => openCreate(ENTITY.program)}
                className="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-[#00628b] text-white text-sm"
              >
                <Plus size={16} /> Program
              </button>
            </>
          )}
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-7 gap-3 mb-6">
        {[
          { label: 'Campuses', value: counts.campuses, icon: Building2 },
          { label: 'Colleges', value: counts.colleges, icon: Landmark },
          { label: 'Schools', value: counts.schools, icon: School },
          { label: 'Programs', value: counts.programs, icon: BookOpen },
          { label: 'Modules', value: counts.modules, icon: CheckCircle2 },
          { label: 'Intakes', value: counts.intakes, icon: Users },
          { label: 'Groups', value: counts.groups, icon: Users },
        ].map((stat) => {
          const Icon = stat.icon;
          return (
            <div key={stat.label} className="bg-white rounded-xl border border-gray-200 px-4 py-3 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-xs text-gray-500">{stat.label}</span>
                <Icon size={16} className="text-[#00628b]" />
              </div>
              <p className="m-0 mt-1 text-xl font-bold text-gray-900">{loading ? '—' : stat.value ?? 0}</p>
            </div>
          );
        })}
      </div>

      {/* Campuses strip */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm mb-6 overflow-hidden">
        <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100">
          <h2 className="m-0 text-sm font-semibold text-[#031f50]">Campuses</h2>
          {canManage && (
            <button
              type="button"
              onClick={() => openCreate(ENTITY.campus)}
              className="inline-flex items-center gap-1 text-xs font-medium text-[#00628b] hover:underline"
            >
              <Plus size={14} /> Add campus
            </button>
          )}
        </div>
        <div className="p-4 flex flex-wrap gap-2">
          {(data.campuses || []).length === 0 ? (
            <p className="m-0 text-sm text-gray-500 italic">No campuses yet.</p>
          ) : (
            (data.campuses || []).map((campus) => (
              <div
                key={campus.id}
                className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm"
              >
                <span className="font-medium text-gray-800 capitalize">{campus.name}</span>
                {canManage && (
                  <span className="inline-flex">
                    <button type="button" className="p-1 text-[#00628b]" onClick={() => openEdit(ENTITY.campus, campus)}>
                      <Pencil size={13} />
                    </button>
                    <button
                      type="button"
                      className="p-1 text-red-600"
                      onClick={() => setDeleteTarget({ entity: ENTITY.campus, row: campus })}
                    >
                      <Trash2 size={13} />
                    </button>
                  </span>
                )}
              </div>
            ))
          )}
        </div>
      </div>

      {/* Structure table — PHP campus.php layout */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm border-collapse">
            <thead>
              <tr className="bg-[#031f50] text-white">
                <th className="text-left font-semibold px-4 py-3 border border-[#1a3a6e] w-[22%]">College</th>
                <th className="text-left font-semibold px-4 py-3 border border-[#1a3a6e] w-[24%]">School / center</th>
                <th className="text-left font-semibold px-4 py-3 border border-[#1a3a6e] w-[40%]">Programs</th>
                <th className="text-left font-semibold px-4 py-3 border border-[#1a3a6e] w-[14%]">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={4} className="px-4 py-10 text-center text-gray-500 border border-gray-200">
                    Loading institutional structure…
                  </td>
                </tr>
              ) : structureRows.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-10 text-center text-gray-500 italic border border-gray-200">
                    No colleges / schools / programs yet. Use the buttons above to add them.
                  </td>
                </tr>
              ) : (
                structureRows.map((row, idx) => (
                  <tr key={row.key} className={idx % 2 === 0 ? 'bg-white' : 'bg-[#f8f9fa]'}>
                    {row.showCollege && (
                      <td
                        rowSpan={row.collegeRowspan}
                        className="align-top px-4 py-3 border border-gray-200 bg-white"
                      >
                        <div className="font-semibold text-[#031f50]">{row.college.name}</div>
                        <div className="text-xs text-gray-500 mt-0.5">{row.college.fullName}</div>
                        {canManage && (
                          <div className="mt-2 flex gap-1">
                            <button
                              type="button"
                              className="p-1 rounded text-[#00628b] hover:bg-blue-50"
                              onClick={() => openEdit(ENTITY.college, row.college)}
                              title="Edit college"
                            >
                              <Pencil size={14} />
                            </button>
                            <button
                              type="button"
                              className="p-1 rounded text-red-600 hover:bg-red-50"
                              onClick={() => setDeleteTarget({ entity: ENTITY.college, row: row.college })}
                              title="Delete college"
                            >
                              <Trash2 size={14} />
                            </button>
                            <button
                              type="button"
                              className="p-1 rounded text-[#00628b] hover:bg-blue-50"
                              onClick={() => openCreate(ENTITY.school, { collegeId: row.college.id })}
                              title="Add school"
                            >
                              <Plus size={14} />
                            </button>
                          </div>
                        )}
                      </td>
                    )}

                    {row.showSchool && (
                      <td
                        rowSpan={row.schoolRowspan}
                        className="align-top px-4 py-3 border border-gray-200"
                      >
                        {row.school ? (
                          <>
                            <div className="font-medium text-[#031f50]">{row.school.name}</div>
                            {canManage && (
                              <div className="mt-2 flex gap-1">
                                <button
                                  type="button"
                                  className="p-1 rounded text-[#00628b] hover:bg-blue-50"
                                  onClick={() => openEdit(ENTITY.school, row.school)}
                                >
                                  <Pencil size={14} />
                                </button>
                                <button
                                  type="button"
                                  className="p-1 rounded text-red-600 hover:bg-red-50"
                                  onClick={() => setDeleteTarget({ entity: ENTITY.school, row: row.school })}
                                >
                                  <Trash2 size={14} />
                                </button>
                                <button
                                  type="button"
                                  className="p-1 rounded text-[#00628b] hover:bg-blue-50"
                                  onClick={() => openCreate(ENTITY.program, { schoolId: row.school.id })}
                                  title="Add program"
                                >
                                  <Plus size={14} />
                                </button>
                              </div>
                            )}
                          </>
                        ) : (
                          <span className="text-gray-400 italic">No school</span>
                        )}
                      </td>
                    )}

                    <td className="align-top px-4 py-3 border border-gray-200">
                      {row.program ? (
                        <div>
                          <div className="flex items-start gap-2">
                            <div className="min-w-0">
                              <p className="m-0 font-medium text-[#031f50]">{row.program.name}</p>
                              {row.program.code ? (
                                <p className="m-0 text-xs text-gray-500 mt-0.5">{row.program.code}</p>
                              ) : null}
                              <p className="m-0 text-[11px] text-gray-400 mt-0.5">
                                {row.program.moduleCount || 0} modules
                                {(row.program.intakeCount || 0) > 0
                                  ? ` · ${row.program.intakeCount} intake(s) · ${row.program.groupCount || 0} groups`
                                  : ''}
                              </p>
                            </div>
                            {row.program.moduleCount > 0 && (
                              <button
                                type="button"
                                className="shrink-0 inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-2 py-1 text-[10px] font-semibold hover:bg-emerald-100"
                                title={`View ${row.program.moduleCount} modules by year`}
                                onClick={() => openModules(row.program)}
                              >
                                <BookOpen size={12} />
                                {row.program.moduleCount}
                              </button>
                            )}
                            {canManage && (!row.program.moduleCount || row.program.moduleCount === 0) && (
                              <button
                                type="button"
                                className="shrink-0 text-[10px] font-semibold text-[#00628b] underline"
                                onClick={() => openModules(row.program)}
                              >
                                Modules
                              </button>
                            )}
                          </div>
                          {(row.program.intakes || []).length > 0 && (
                            <ul className="m-0 mt-2 p-0 list-none space-y-1.5">
                              {row.program.intakes.map((intake) => (
                                <li
                                  key={intake.id}
                                  className="rounded-md border border-gray-100 bg-gray-50 px-2 py-1.5 text-[11px] text-gray-700"
                                >
                                  <span className="font-semibold text-[#031f50]">
                                    Year {intake.yearOfStudy}
                                  </span>
                                  <span className="text-gray-400"> · </span>
                                  <span className="capitalize">{intake.campus?.name || '—'}</span>
                                  <span className="text-gray-400"> · </span>
                                  <span>{intake.size || 0} students</span>
                                  <div className="mt-0.5 text-gray-600">
                                    {(intake.groups || []).length
                                      ? intake.groups
                                          .map((g) => `${g.name} (${g.size || 0})`)
                                          .join(', ')
                                      : 'No groups'}
                                  </div>
                                </li>
                              ))}
                            </ul>
                          )}
                        </div>
                      ) : (
                        <span className="text-gray-400 italic">No program</span>
                      )}
                    </td>

                    <td className="align-top px-4 py-3 border border-gray-200 whitespace-nowrap">
                      {row.program && canManage ? (
                        <div className="flex gap-1">
                          <button
                            type="button"
                            className="p-1.5 rounded text-[#00628b] hover:bg-blue-50"
                            onClick={() => openEdit(ENTITY.program, row.program)}
                          >
                            <Pencil size={14} />
                          </button>
                          <button
                            type="button"
                            className="p-1.5 rounded text-red-600 hover:bg-red-50"
                            onClick={() => setDeleteTarget({ entity: ENTITY.program, row: row.program })}
                          >
                            <Trash2 size={14} />
                          </button>
                          <Link
                            to={`${appPath('modules')}?programId=${row.program.id}`}
                            className="p-1.5 rounded text-gray-600 hover:bg-gray-100 text-xs font-medium"
                            title="Manage modules"
                          >
                            Modules
                          </Link>
                        </div>
                      ) : (
                        <span className="text-gray-300">—</span>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      <EntityModal
        open={modal.open}
        title={`${modal.editing ? 'Edit' : 'Add'} ${modal.entity || ''}`}
        fields={modalFields}
        form={form}
        setForm={setForm}
        onClose={() => setModal({ open: false, entity: null, editing: null })}
        onSave={handleSave}
        saving={saving}
      />

      <ModulesBrowserModal
        open={modulesDrawer.open}
        program={modulesDrawer.program}
        modules={modulesDrawer.modules}
        loading={modulesDrawer.loading}
        canManage={canManage}
        onClose={() => setModulesDrawer({ open: false, program: null, modules: [], loading: false })}
      />

      <ModalShell
        open={Boolean(deleteTarget)}
        onClose={() => setDeleteTarget(null)}
        size="sm"
        accent="danger"
        title="Confirm delete"
        subtitle="Related records may block deletion if they still exist"
        bodyClassName="px-4 sm:px-5 py-5 text-center"
        footer={
          <>
            <ModalSecondaryButton onClick={() => setDeleteTarget(null)}>Cancel</ModalSecondaryButton>
            <ModalPrimaryButton danger disabled={saving} onClick={handleDelete}>
              {saving ? 'Deleting…' : 'Delete'}
            </ModalPrimaryButton>
          </>
        }
      >
        <p className="m-0 text-sm text-slate-600">
          Delete{' '}
          <span className="font-semibold text-[#031f50]">
            {deleteTarget?.row?.name || deleteTarget?.row?.fullName || `#${deleteTarget?.row?.id}`}
          </span>
          ?
        </p>
      </ModalShell>
    </div>
  );
}
