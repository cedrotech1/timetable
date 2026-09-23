import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
  Plus,
  Pencil,
  Trash2,
  Search,
  BookOpen,
  GraduationCap,
  ArrowLeft,
  RefreshCw,
  Eraser,
} from 'lucide-react';
import { modulesService, programsService } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import { useNotification } from '../contexts/NotificationContext';
import { canManageOrg, isAdmin } from '../utils/roles';
import { appPath } from '../utils/appPaths';
import ModalShell, { ModalPrimaryButton, ModalSecondaryButton } from '../components/ModalShell';

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

const emptyForm = (defaults = {}) => ({
  name: '',
  code: '',
  credits: 10,
  year: 1,
  semester: '1',
  programId: '',
  ...defaults,
});

export default function ModulesPage() {
  const [searchParams] = useSearchParams();
  const filterProgramId = searchParams.get('programId') || '';
  const { user } = useAuth();
  const { showSuccess, showError } = useNotification();
  const canManage = canManageOrg(user?.role);
  const admin = isAdmin(user?.role);

  const [programs, setPrograms] = useState([]);
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [q, setQ] = useState('');
  const [yearFilter, setYearFilter] = useState('all');
  const [semFilter, setSemFilter] = useState('all');
  const [programFilter, setProgramFilter] = useState(filterProgramId);

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(emptyForm());
  const [saving, setSaving] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);

  useEffect(() => {
    setProgramFilter(filterProgramId);
  }, [filterProgramId]);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const params = programFilter ? { programId: programFilter } : {};
      const [modRes, progRes] = await Promise.all([
        modulesService.getAll(params),
        programsService.getAll(),
      ]);
      if (modRes?.success) setRows(modRes.data || []);
      else showError(modRes?.message || 'Failed to load modules');
      if (progRes?.success) setPrograms(progRes.data || []);
    } catch (error) {
      showError(error.response?.data?.message || 'Failed to load modules');
    } finally {
      setLoading(false);
    }
  }, [programFilter, showError]);

  useEffect(() => {
    load();
  }, [load]);

  const filtered = useMemo(() => {
    const query = q.trim().toLowerCase();
    return rows.filter((m) => {
      if (yearFilter !== 'all' && String(m.year) !== String(yearFilter)) return false;
      if (semFilter !== 'all' && String(m.semester) !== String(semFilter)) return false;
      if (!query) return true;
      const hay = `${m.code || ''} ${m.name || ''} ${m.program?.name || ''}`.toLowerCase();
      return hay.includes(query);
    });
  }, [rows, q, yearFilter, semFilter]);

  const grouped = useMemo(() => groupModulesByYearSemester(filtered), [filtered]);

  const yearOptions = useMemo(() => {
    const set = new Set(rows.map((m) => Number(m.year) || 0));
    return [...set].filter((y) => y > 0).sort((a, b) => a - b);
  }, [rows]);

  const semOptions = useMemo(() => {
    const set = new Set(rows.map((m) => String(m.semester ?? '1')));
    return [...set].sort((a, b) => Number(a) - Number(b));
  }, [rows]);

  const programName = programs.find((p) => String(p.id) === String(programFilter))?.name;

  const openCreate = (defaults = {}) => {
    setEditing(null);
    setForm(
      emptyForm({
        programId: programFilter ? Number(programFilter) : '',
        year: defaults.year ?? 1,
        semester: defaults.semester ?? '1',
      })
    );
    setModalOpen(true);
  };

  const openEdit = (row) => {
    setEditing(row);
    setForm({
      name: row.name || '',
      code: row.code || '',
      credits: row.credits ?? 10,
      year: row.year ?? 1,
      semester: String(row.semester ?? '1'),
      programId: row.programId || row.program?.id || '',
    });
    setModalOpen(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    try {
      setSaving(true);
      const payload = {
        name: form.name,
        code: form.code || null,
        credits: Number(form.credits) || 0,
        year: Number(form.year),
        semester: String(form.semester),
        programId: Number(form.programId),
      };
      const response = editing
        ? await modulesService.update(editing.id, payload)
        : await modulesService.create(payload);
      if (response?.success) {
        showSuccess(editing ? 'Module updated' : 'Module created');
        setModalOpen(false);
        setEditing(null);
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
    try {
      setSaving(true);
      const response = await modulesService.remove(deleteTarget.id);
      if (response?.success) {
        showSuccess('Module deleted');
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

  const handleTruncateAll = async () => {
    if (!admin) return;
    const ok = window.confirm(
      `Delete ALL ${rows.length} module(s)?\n\nLinked teaching plans will also be removed.\nYou can recreate modules by re-uploading Excel on Set timetable (Rematch).\n\nContinue?`
    );
    if (!ok) return;
    try {
      setSaving(true);
      const response = await modulesService.truncateAll();
      if (response?.success) {
        showSuccess(response.message || 'All modules truncated');
        await load();
      } else {
        showError(response?.message || 'Truncate failed');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Truncate failed');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="max-w-6xl mx-auto space-y-4">
      <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
          <Link
            to={appPath('organization')}
            className="inline-flex items-center gap-1 text-xs font-semibold text-[#00628b] hover:underline mb-1"
          >
            <ArrowLeft size={12} /> Organization
          </Link>
          <h1 className="m-0 text-2xl font-bold text-[#031f50] flex items-center gap-2">
            <BookOpen size={22} className="text-[#00628b]" />
            Modules
          </h1>
          <p className="m-0 mt-1 text-sm text-slate-500">
            Organized by year of study and semester
            {programName ? ` · ${programName}` : ''}
          </p>
        </div>
        {canManage && (
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => load()}
              disabled={loading || saving}
              className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium hover:bg-slate-50 disabled:opacity-50"
              title="Reload modules from the database"
            >
              <RefreshCw size={15} /> Reload
            </button>
            {admin && (
              <button
                type="button"
                onClick={handleTruncateAll}
                disabled={loading || saving || !rows.length}
                className="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2.5 text-sm font-medium hover:bg-red-100 disabled:opacity-50"
                title="Delete all modules (Excel rematch can recreate them)"
              >
                <Eraser size={15} /> Truncate all
              </button>
            )}
            <button
              type="button"
              onClick={() => openCreate()}
              className="inline-flex items-center gap-2 rounded-xl bg-[#00628b] text-white px-4 py-2.5 text-sm font-semibold hover:bg-[#004f70]"
            >
              <Plus size={16} /> Add module
            </button>
          </div>
        )}
      </div>

      {/* Filters */}
      <div className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-3">
        <div className="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3">
          <div className="relative">
            <Search size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#00628b]/70" />
            <input
              type="search"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Search code, name, or program…"
              className="w-full rounded-xl border border-slate-200 bg-[#f8fafc] pl-10 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/20"
            />
          </div>
          <select
            value={programFilter}
            onChange={(e) => setProgramFilter(e.target.value)}
            className="rounded-xl border border-slate-200 px-3 py-2.5 text-sm min-w-[12rem]"
          >
            <option value="">All programs</option>
            {programs.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Year</span>
          <button
            type="button"
            onClick={() => setYearFilter('all')}
            className={`px-2.5 py-1 rounded-full text-[11px] font-semibold border ${
              yearFilter === 'all' ? 'bg-[#031f50] text-white border-[#031f50]' : 'bg-white border-slate-200'
            }`}
          >
            All
          </button>
          {yearOptions.map((y) => (
            <button
              key={y}
              type="button"
              onClick={() => setYearFilter(String(y))}
              className={`px-2.5 py-1 rounded-full text-[11px] font-semibold border ${
                String(yearFilter) === String(y)
                  ? 'bg-[#c45c26] text-white border-[#c45c26]'
                  : 'bg-white border-slate-200'
              }`}
            >
              Year {y}
            </button>
          ))}
          <span className="text-[10px] uppercase tracking-wider text-slate-400 font-semibold ml-2">Sem</span>
          <button
            type="button"
            onClick={() => setSemFilter('all')}
            className={`px-2.5 py-1 rounded-full text-[11px] font-semibold border ${
              semFilter === 'all' ? 'bg-[#031f50] text-white border-[#031f50]' : 'bg-white border-slate-200'
            }`}
          >
            All
          </button>
          {semOptions.map((s) => (
            <button
              key={s}
              type="button"
              onClick={() => setSemFilter(String(s))}
              className={`px-2.5 py-1 rounded-full text-[11px] font-semibold border ${
                String(semFilter) === String(s)
                  ? 'bg-[#00628b] text-white border-[#00628b]'
                  : 'bg-white border-slate-200'
              }`}
            >
              Sem {s}
            </button>
          ))}
          <span className="ml-auto text-[11px] text-slate-500">{filtered.length} modules</span>
        </div>
      </div>

      {loading ? (
        <p className="text-sm text-slate-500 text-center py-16">Loading modules…</p>
      ) : grouped.length === 0 ? (
        <div className="bg-white rounded-2xl border border-dashed border-slate-200 py-16 text-center">
          <BookOpen className="mx-auto text-slate-300 mb-3" size={28} />
          <p className="m-0 text-sm text-slate-600">No modules found</p>
          {canManage && (
            <button
              type="button"
              onClick={() => openCreate()}
              className="mt-3 text-sm font-semibold text-[#00628b] underline"
            >
              Add the first module
            </button>
          )}
        </div>
      ) : (
        <div className="space-y-4">
          {grouped.map((yearBlock) => (
            <section
              key={yearBlock.year}
              className="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm"
            >
              <header className="flex items-center justify-between gap-2 px-4 py-3 bg-gradient-to-r from-[#031f50] to-[#00628b] text-white">
                <div className="flex items-center gap-2">
                  <GraduationCap size={16} />
                  <h2 className="m-0 text-sm font-semibold">
                    {yearBlock.year > 0 ? `Year ${yearBlock.year}` : 'Year not set'}
                  </h2>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-[11px] bg-white/15 px-2 py-0.5 rounded-full">
                    {yearBlock.total} module{yearBlock.total === 1 ? '' : 's'}
                  </span>
                  {canManage && (
                    <button
                      type="button"
                      onClick={() => openCreate({ year: yearBlock.year || 1, semester: '1' })}
                      className="inline-flex items-center gap-1 text-[11px] font-semibold bg-white/15 hover:bg-white/25 px-2 py-1 rounded-lg"
                    >
                      <Plus size={12} /> Add
                    </button>
                  )}
                </div>
              </header>

              <div className="divide-y divide-slate-100">
                {yearBlock.semesters.map((semBlock) => (
                  <div key={`${yearBlock.year}-${semBlock.semester}`} className="p-4">
                    <div className="flex items-center justify-between gap-2 mb-3">
                      <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-[#fff4eb] text-[#c45c26] border border-[#e8a05c]/50">
                        Semester {semBlock.semester}
                      </span>
                      {canManage && (
                        <button
                          type="button"
                          onClick={() =>
                            openCreate({ year: yearBlock.year || 1, semester: semBlock.semester })
                          }
                          className="text-[11px] font-semibold text-[#00628b] hover:underline"
                        >
                          + Module in Sem {semBlock.semester}
                        </button>
                      )}
                    </div>
                    <ul className="m-0 p-0 list-none grid grid-cols-1 lg:grid-cols-2 gap-2">
                      {semBlock.modules.map((m) => (
                        <li
                          key={m.id}
                          className="group flex items-start justify-between gap-3 rounded-xl border border-slate-100 bg-[#f8fafc] hover:border-[#e8a05c]/40 hover:bg-[#fff8f2] px-3 py-3 transition"
                        >
                          <div className="min-w-0">
                            <p className="m-0 text-sm font-semibold text-[#031f50]">{m.name}</p>
                            <p className="m-0 mt-1 flex flex-wrap gap-1.5 text-[11px] text-slate-500">
                              {m.code ? (
                                <span className="font-mono font-semibold text-[#00628b] bg-[#e8f4f8] px-1.5 py-0.5 rounded">
                                  {m.code}
                                </span>
                              ) : null}
                              <span>{m.credits ?? 0} credits</span>
                              {!programFilter && m.program?.name ? (
                                <>
                                  <span>·</span>
                                  <span className="truncate max-w-[14rem]">{m.program.name}</span>
                                </>
                              ) : null}
                            </p>
                          </div>
                          {canManage && (
                            <div className="flex gap-1 shrink-0 opacity-70 group-hover:opacity-100">
                              <button
                                type="button"
                                className="p-1.5 rounded-lg text-[#00628b] hover:bg-white"
                                onClick={() => openEdit(m)}
                                title="Edit"
                              >
                                <Pencil size={14} />
                              </button>
                              <button
                                type="button"
                                className="p-1.5 rounded-lg text-red-600 hover:bg-white"
                                onClick={() => setDeleteTarget(m)}
                                title="Delete"
                              >
                                <Trash2 size={14} />
                              </button>
                            </div>
                          )}
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </div>
            </section>
          ))}
        </div>
      )}

      {/* Add / Edit modal */}
      <ModalShell
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        size="md"
        accent="amber"
        title={editing ? 'Edit module' : 'Add module'}
        subtitle="Assign year of study and semester for timetable planning"
        bodyClassName="px-4 sm:px-5 py-4"
        footer={
          <>
            <ModalSecondaryButton onClick={() => setModalOpen(false)}>Cancel</ModalSecondaryButton>
            <ModalPrimaryButton type="submit" form="module-form" disabled={saving}>
              {saving ? 'Saving…' : 'Save module'}
            </ModalPrimaryButton>
          </>
        }
      >
        <form id="module-form" onSubmit={handleSave} className="space-y-4">
          <div>
            <label className="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">
              Module name
            </label>
            <input
              required
              value={form.name}
              onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
              className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25"
              placeholder="e.g. Business Mathematics"
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">
                Code
              </label>
              <input
                value={form.code}
                onChange={(e) => setForm((p) => ({ ...p, code: e.target.value }))}
                className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#00628b]/25"
                placeholder="AF80133"
              />
            </div>
            <div>
              <label className="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">
                Credits
              </label>
              <input
                type="number"
                required
                min={0}
                value={form.credits}
                onChange={(e) => setForm((p) => ({ ...p, credits: e.target.value }))}
                className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25"
              />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">
                Year of study
              </label>
              <select
                required
                value={form.year}
                onChange={(e) => setForm((p) => ({ ...p, year: Number(e.target.value) }))}
                className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#c45c26]/25"
              >
                {[1, 2, 3, 4, 5].map((y) => (
                  <option key={y} value={y}>
                    Year {y}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">
                Semester
              </label>
              <select
                required
                value={form.semester}
                onChange={(e) => setForm((p) => ({ ...p, semester: e.target.value }))}
                className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25"
              >
                <option value="1">Semester 1</option>
                <option value="2">Semester 2</option>
                <option value="3">Semester 3</option>
              </select>
            </div>
          </div>
          <div>
            <label className="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">
              Program
            </label>
            <select
              required
              value={form.programId}
              onChange={(e) => setForm((p) => ({ ...p, programId: e.target.value }))}
              className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25"
            >
              <option value="">Select program…</option>
              {programs.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </div>
        </form>
      </ModalShell>

      <ModalShell
        open={Boolean(deleteTarget)}
        onClose={() => setDeleteTarget(null)}
        size="sm"
        accent="danger"
        title="Delete module"
        subtitle="This cannot be undone if the module is unused"
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
          Delete <span className="font-semibold text-[#031f50]">{deleteTarget?.name}</span>
          {deleteTarget?.code ? ` (${deleteTarget.code})` : ''}?
        </p>
      </ModalShell>
    </div>
  );
}
