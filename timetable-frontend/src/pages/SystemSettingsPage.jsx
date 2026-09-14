import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Settings,
  AlertTriangle,
  Trash2,
  Users,
  CalendarRange,
  Plus,
  RefreshCw,
} from 'lucide-react';
import { timetableService } from '../services/api';
import { useNotification } from '../contexts/NotificationContext';
import { useAuth } from '../contexts/AuthContext';
import { canManageOrg, isAdmin } from '../utils/roles';
import { appPath } from '../utils/appPaths';

export default function SystemSettingsPage() {
  const { user } = useAuth();
  const { showSuccess, showError } = useNotification();
  const canManage = canManageOrg(user?.role);
  const admin = isAdmin(user?.role);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [settings, setSettings] = useState(null);
  const [years, setYears] = useState([]);
  const [counts, setCounts] = useState({ timetables: 0, sessions: 0, intakes: 0, groups: 0 });

  const [status, setStatus] = useState('live');
  const [academicYearId, setAcademicYearId] = useState('');
  const [semester, setSemester] = useState('1');
  const [newYearLabel, setNewYearLabel] = useState('');
  const [tab, setTab] = useState('system');

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await timetableService.getSettings();
      const data = res?.data || {};
      const s = data.settings;
      setSettings(s);
      setYears(data.academicYears || []);
      setCounts(data.counts || { timetables: 0, sessions: 0, intakes: 0, groups: 0 });
      setStatus(s?.status || 'live');
      setAcademicYearId(String(s?.academicYearId || ''));
      setSemester(String(s?.semester || '1'));
    } catch (error) {
      showError(error.response?.data?.message || 'Failed to load settings');
    } finally {
      setLoading(false);
    }
  }, [showError]);

  useEffect(() => {
    load();
  }, [load]);

  if (!canManage) {
    return (
      <div className="max-w-3xl mx-auto bg-white rounded-xl border p-8 text-center">
        <h1 className="text-xl font-bold text-gray-900">Access denied</h1>
        <p className="text-sm text-gray-500 mt-2">Only admin / dean / registrar can open system settings.</p>
      </div>
    );
  }

  const saveSettings = async (e) => {
    e.preventDefault();
    try {
      setSaving(true);
      const res = await timetableService.updateSettings({
        status,
        academicYearId: Number(academicYearId),
        semester,
      });
      if (res?.success) {
        showSuccess('System settings updated');
        await load();
      } else {
        showError(res?.message || 'Update failed');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Update failed');
    } finally {
      setSaving(false);
    }
  };

  const addYear = async (e) => {
    e.preventDefault();
    if (!admin) return;
    try {
      setSaving(true);
      const res = await timetableService.createAcademicYear({ yearLabel: newYearLabel });
      if (res?.success) {
        showSuccess('Academic year added');
        setNewYearLabel('');
        await load();
      } else {
        showError(res?.message || 'Failed to add year');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Failed to add year');
    } finally {
      setSaving(false);
    }
  };

  const removeYear = async (id) => {
    if (!admin) return;
    if (!window.confirm('Delete this academic year?')) return;
    try {
      setSaving(true);
      const res = await timetableService.deleteAcademicYear(id);
      if (res?.success) {
        showSuccess('Academic year deleted');
        await load();
      } else {
        showError(res?.message || 'Delete failed');
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Delete failed');
    } finally {
      setSaving(false);
    }
  };

  const resetTimetables = async () => {
    if (!admin) return;
    const ok = window.confirm(
      'This will erase ALL timetable data (plans, sessions, lecturers, group links).\n\nAre you sure you want to continue?'
    );
    if (!ok) return;
    try {
      setSaving(true);
      const res = await timetableService.resetTimetables();
      showSuccess(res?.message || 'Timetables reset');
      await load();
    } catch (error) {
      showError(error.response?.data?.message || 'Reset failed');
    } finally {
      setSaving(false);
    }
  };

  const clearPromotions = async () => {
    if (!admin) return;
    const ok = window.confirm(
      'Delete ALL promotions/intakes and student groups?\n\nLinked timetable plans for those groups will also be deleted.\nYou can recreate groups from Import Timetable Excel.\n\nContinue?'
    );
    if (!ok) return;
    try {
      setSaving(true);
      const res = await timetableService.clearIntakesGroups();
      showSuccess(res?.message || 'Intakes & groups cleared');
      await load();
    } catch (error) {
      showError(error.response?.data?.message || 'Clear failed');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <div className="text-center text-gray-500 py-16">Loading settings…</div>;
  }

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[#031f50] m-0 flex items-center gap-2">
            <Settings size={22} className="text-[#00628b]" />
            System settings
          </h1>
          <p className="mt-1 mb-0 text-sm text-gray-500">
            Academic year, semester, and dangerous resets.
          </p>
        </div>
        <button
          type="button"
          onClick={load}
          className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border text-sm"
        >
          <RefreshCw size={14} /> Refresh
        </button>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {[
          { label: 'Timetables', value: counts.timetables },
          { label: 'Sessions', value: counts.sessions },
          { label: 'Intakes', value: counts.intakes },
          { label: 'Groups', value: counts.groups },
        ].map((c) => (
          <div key={c.label} className="bg-white rounded-xl border px-4 py-3 shadow-sm">
            <p className="m-0 text-xs text-gray-500">{c.label}</p>
            <p className="m-0 mt-1 text-xl font-bold text-gray-900">{c.value ?? 0}</p>
          </div>
        ))}
      </div>

      <div className="flex gap-2 border-b border-gray-200">
        <button
          type="button"
          onClick={() => setTab('system')}
          className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px ${
            tab === 'system' ? 'border-[#00628b] text-[#00628b]' : 'border-transparent text-gray-500'
          }`}
        >
          System settings
        </button>
        <button
          type="button"
          onClick={() => setTab('years')}
          className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px ${
            tab === 'years' ? 'border-[#00628b] text-[#00628b]' : 'border-transparent text-gray-500'
          }`}
        >
          Academic years
        </button>
      </div>

      {tab === 'system' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="bg-white rounded-xl border border-red-200 p-5 shadow-sm">
              <h2 className="m-0 text-base font-semibold text-red-700 flex items-center gap-2">
                <Trash2 size={16} /> Reset timetables
              </h2>
              <p className="mt-2 mb-3 text-sm text-gray-600">
                Permanently clears every timetable record along with associated groups, lecturers, and
                sessions. Proceed only if you have a backup.
              </p>
              <p className="text-xs text-gray-500 mb-3">
                Current: <strong>{counts.timetables}</strong> plan(s), <strong>{counts.sessions}</strong>{' '}
                session(s)
              </p>
              <button
                type="button"
                disabled={!admin || saving}
                onClick={resetTimetables}
                className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold disabled:opacity-50"
              >
                <AlertTriangle size={14} /> Reset all timetables
              </button>
              {!admin && (
                <p className="m-0 mt-2 text-xs text-gray-400">Admin only</p>
              )}
            </div>

            <div className="bg-white rounded-xl border border-amber-300 p-5 shadow-sm">
              <h2 className="m-0 text-base font-semibold text-amber-800 flex items-center gap-2">
                <Users size={16} /> Clear promotions / intakes &amp; groups
              </h2>
              <p className="mt-2 mb-3 text-sm text-gray-600">
                Removes all promotions (intakes) and student groups so you can recreate them from Excel
                import. Linked teaching plans for those groups are also removed.
              </p>
              <p className="text-xs text-gray-500 mb-3">
                Current: <strong>{counts.intakes}</strong> intake(s), <strong>{counts.groups}</strong>{' '}
                group(s)
              </p>
              <button
                type="button"
                disabled={!admin || saving}
                onClick={clearPromotions}
                className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-semibold disabled:opacity-50"
              >
                <Users size={14} /> Clear intakes &amp; groups
              </button>
              <p className="m-0 mt-2 text-xs text-gray-500">
                After clearing, open{' '}
                <Link to={appPath('set-timetable')} className="text-[#00628b] hover:underline">
                  Set timetable → Excel upload
                </Link>{' '}
                → Save all intakes &amp; groups.
              </p>
              {!admin && (
                <p className="m-0 mt-1 text-xs text-gray-400">Admin only</p>
              )}
            </div>
          </div>

          <form onSubmit={saveSettings} className="bg-white rounded-xl border p-5 shadow-sm space-y-4">
            <h2 className="m-0 text-base font-semibold text-[#031f50] flex items-center gap-2">
              <CalendarRange size={16} /> Live academic period
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">System status</label>
                <select
                  value={status}
                  onChange={(e) => setStatus(e.target.value)}
                  className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                >
                  <option value="live">Live</option>
                  <option value="maintenance">Maintenance</option>
                  <option value="offline">Offline</option>
                  <option value="development">Development</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Academic year</label>
                <select
                  value={academicYearId}
                  onChange={(e) => setAcademicYearId(e.target.value)}
                  required
                  className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                >
                  <option value="">Select…</option>
                  {years.map((y) => (
                    <option key={y.id} value={y.id}>
                      {y.yearLabel}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Semester</label>
                <select
                  value={semester}
                  onChange={(e) => setSemester(e.target.value)}
                  className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                >
                  <option value="1">Semester 1</option>
                  <option value="2">Semester 2</option>
                  <option value="3">Semester 3</option>
                </select>
              </div>
            </div>
            <p className="m-0 text-xs text-gray-400">
              Current: {settings?.academicYear?.yearLabel || academicYearId} · Sem {settings?.semester}
            </p>
            <button
              type="submit"
              disabled={saving}
              className="px-4 py-2 rounded-lg bg-[#00628b] text-white text-sm font-semibold disabled:opacity-50"
            >
              {saving ? 'Saving…' : 'Update system settings'}
            </button>
          </form>
        </div>
      )}

      {tab === 'years' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <form onSubmit={addYear} className="bg-white rounded-xl border p-5 shadow-sm space-y-3">
            <h2 className="m-0 text-base font-semibold text-[#031f50]">Add academic year</h2>
            <input
              type="text"
              required
              disabled={!admin}
              value={newYearLabel}
              onChange={(e) => setNewYearLabel(e.target.value)}
              placeholder="e.g. 2026-2027"
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
            />
            <button
              type="submit"
              disabled={!admin || saving}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#00628b] text-white text-sm font-semibold disabled:opacity-50"
            >
              <Plus size={14} /> Add year
            </button>
            {!admin && <p className="m-0 text-xs text-gray-400">Admin only</p>}
          </form>

          <div className="bg-white rounded-xl border p-5 shadow-sm">
            <h2 className="m-0 text-base font-semibold text-[#031f50] mb-3">Academic years</h2>
            <ul className="m-0 p-0 list-none divide-y divide-gray-100">
              {years.map((y) => (
                <li key={y.id} className="flex items-center justify-between py-2 text-sm">
                  <span>
                    {y.yearLabel}
                    {Number(y.id) === Number(settings?.academicYearId) ? (
                      <span className="ml-2 text-[11px] text-emerald-700">(active)</span>
                    ) : null}
                  </span>
                  {admin && Number(y.id) !== Number(settings?.academicYearId) && (
                    <button
                      type="button"
                      className="text-red-600 text-xs"
                      onClick={() => removeYear(y.id)}
                    >
                      Delete
                    </button>
                  )}
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}
    </div>
  );
}
