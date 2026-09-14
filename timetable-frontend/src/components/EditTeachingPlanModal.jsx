import { useCallback, useEffect, useMemo, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import {
  timetableService,
  modulesService,
  facilitiesService,
} from '../services/api';
import SearchablePicker, { PickerButton } from './SearchablePicker';
import ModalShell, { ModalPrimaryButton, ModalSecondaryButton } from './ModalShell';
import { ConflictBoxWithPlanViewer } from './ConflictBox';

const DEFAULT_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

function toTimeInput(t) {
  return String(t || '').slice(0, 5);
}

/**
 * Edit a saved teaching plan: module, facility, lecturers, sessions, groups.
 * Saves via PUT and shows facility/group conflicts without writing.
 */
export default function EditTeachingPlanModal({
  open,
  plan,
  meta,
  canDelete = false,
  onClose,
  onSaved,
  onDeleted,
  showError,
  showSuccess,
  showWarning,
  zIndex = 55,
}) {
  const [modules, setModules] = useState([]);
  const [facilities, setFacilities] = useState([]);
  const [availableFacilities, setAvailableFacilities] = useState([]);
  const [lecturers, setLecturers] = useState([]);
  const [intakes, setIntakes] = useState([]);
  const [settings, setSettings] = useState(null);
  const [loadingMeta, setLoadingMeta] = useState(false);

  const [moduleId, setModuleId] = useState('');
  const [facilityId, setFacilityId] = useState('');
  const [leaderId, setLeaderId] = useState('');
  const [otherLecturerIds, setOtherLecturerIds] = useState([]);
  const [selectedGroupIds, setSelectedGroupIds] = useState([]);
  const [sessions, setSessions] = useState([{ day: 'Monday', start: '08:00', end: '13:00' }]);
  const [status, setStatus] = useState('pending');
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [conflicts, setConflicts] = useState(null);
  const [picker, setPicker] = useState(null);

  const days = settings?.days || DEFAULT_DAYS;
  const timeBlocks = settings?.timeBlocks || [];
  const academicYearId = meta?.academicYearId || settings?.settings?.academicYearId;
  const semester = meta?.semester || settings?.settings?.semester;

  const allGroups = useMemo(() => {
    const map = new Map();
    for (const intake of intakes) {
      for (const g of intake.groups || []) {
        map.set(Number(g.id), {
          ...g,
          intakeId: intake.id,
          yearOfStudy: intake.yearOfStudy,
          campusName: intake.campus?.name,
          programName: intake.program?.name,
          programId: intake.programId,
        });
      }
    }
    // Keep groups already on the plan even if intake list is incomplete
    for (const g of plan?.groups || []) {
      const id = Number(g.id);
      if (!id || map.has(id)) continue;
      map.set(id, {
        id,
        name: g.name,
        size: g.size,
        yearOfStudy: g.year_of_study,
        campusName: g.campus,
        programName: g.program,
        programId: g.program_id,
      });
    }
    return [...map.values()].sort((a, b) => {
      const byProgram = String(a.programName || '').localeCompare(String(b.programName || ''));
      if (byProgram) return byProgram;
      return String(a.name || '').localeCompare(String(b.name || ''));
    });
  }, [intakes, plan?.groups]);

  const resetFromPlan = useCallback((p) => {
    if (!p) return;
    setModuleId(String(p.module?.id || ''));
    setFacilityId(String(p.facility?.id || ''));
    setLeaderId(p.leader_lecturer?.id ? String(p.leader_lecturer.id) : '');
    setOtherLecturerIds((p.other_lecturers || []).map((l) => Number(l.id)).filter(Boolean));
    setSelectedGroupIds((p.groups || []).map((g) => Number(g.id)).filter(Boolean));
    setStatus(p.status || 'pending');
    const sess = (p.sessions || []).map((s) => ({
      day: s.day || 'Monday',
      start: toTimeInput(s.start_time || s.startTime || s.start),
      end: toTimeInput(s.end_time || s.endTime || s.end),
    }));
    setSessions(sess.length ? sess : [{ day: 'Monday', start: '08:00', end: '13:00' }]);
    setConflicts(null);
  }, []);

  useEffect(() => {
    if (!open || !plan) return;
    resetFromPlan(plan);
    let cancelled = false;
    (async () => {
      try {
        setLoadingMeta(true);
        const [settingsRes, intakesRes, modulesRes, facilitiesRes, lecturersRes] = await Promise.all([
          timetableService.getSettings(),
          timetableService.listIntakes(),
          modulesService.getAll(),
          facilitiesService.getAll(),
          timetableService.listLecturers().catch(() => ({ data: [] })),
        ]);
        if (cancelled) return;
        setSettings(settingsRes?.data || null);
        setIntakes(intakesRes?.data || []);
        setModules(modulesRes?.data || []);
        setFacilities(facilitiesRes?.data || []);
        setLecturers(lecturersRes?.data || []);
      } catch (error) {
        showError?.(error.response?.data?.message || 'Failed to load edit data');
      } finally {
        if (!cancelled) setLoadingMeta(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [open, plan, resetFromPlan, showError]);

  const refreshAvailable = useCallback(async () => {
    if (!open || !academicYearId || !semester || !sessions?.length) return;
    try {
      const res = await timetableService.availableFacilities({
        academicYearId,
        semester,
        sessions,
        excludeTimetableId: plan?.id,
      });
      setAvailableFacilities(res?.data || []);
    } catch {
      setAvailableFacilities([]);
    }
  }, [open, academicYearId, semester, sessions, plan?.id]);

  useEffect(() => {
    refreshAvailable();
  }, [refreshAvailable]);

  const toggleGroup = (id) => {
    const nid = Number(id);
    setSelectedGroupIds((prev) =>
      prev.map(Number).includes(nid) ? prev.filter((x) => Number(x) !== nid) : [...prev, nid]
    );
  };

  const facilityList = availableFacilities.length ? availableFacilities : facilities;

  const save = async () => {
    if (!plan?.id) return;
    if (!moduleId || !facilityId || !selectedGroupIds.length) {
      showError?.('Module, facility, and at least one group are required');
      return;
    }
    setConflicts(null);
    try {
      setSaving(true);
      const result = await timetableService.update(plan.id, {
        moduleId: Number(moduleId),
        facilityId: Number(facilityId),
        leaderLecturerId: leaderId ? Number(leaderId) : null,
        otherLecturerIds,
        groupIds: selectedGroupIds,
        sessions,
        academicYearId,
        semester,
        status,
      });
      if (result?.success) {
        showSuccess?.(result.message || 'Teaching plan updated');
        onSaved?.(result.data);
        onClose?.();
      } else {
        showError?.(result?.message || 'Update failed');
      }
    } catch (error) {
      const data = error.response?.data;
      if (data?.status === 'conflict' || error.response?.status === 409) {
        setConflicts(data.conflicts);
        showWarning?.(data.message || 'Conflicts detected — not saved');
      } else {
        showError?.(data?.message || 'Failed to update teaching plan');
      }
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    if (!plan?.id || !canDelete) return;
    if (!window.confirm(`Delete teaching plan #${plan.id}? This cannot be undone.`)) return;
    try {
      setDeleting(true);
      await timetableService.remove(plan.id);
      showSuccess?.('Teaching plan deleted');
      onDeleted?.(plan.id);
      onClose?.();
    } catch (error) {
      showError?.(error.response?.data?.message || 'Failed to delete');
    } finally {
      setDeleting(false);
    }
  };

  if (!open || !plan) return null;

  const moduleLabel = (() => {
    const m = modules.find((x) => String(x.id) === String(moduleId)) || plan.module;
    return m ? `${m.code ? `${m.code} — ` : ''}${m.name || plan.course || ''}` : null;
  })();
  const facilityLabel = (() => {
    const f = facilityList.find((x) => String(x.id) === String(facilityId)) || plan.facility;
    return f
      ? `${f.name}${f.capacity ? ` (${f.capacity})` : ''}${f.campus?.name ? ` — ${f.campus.name}` : ''}`
      : null;
  })();
  const leaderLabel = (() => {
    const u = lecturers.find((x) => String(x.id) === String(leaderId)) || plan.leader_lecturer;
    return u ? `${u.names}${u.urEmail || u.email ? ` · ${u.urEmail || u.email}` : ''}` : null;
  })();
  const othersLabel = otherLecturerIds.length
    ? lecturers
        .filter((u) => otherLecturerIds.map(String).includes(String(u.id)))
        .map((u) => u.names)
        .join(', ') ||
      (plan.other_lecturers || [])
        .filter((u) => otherLecturerIds.map(String).includes(String(u.id)))
        .map((u) => u.names)
        .join(', ')
    : null;

  return (
    <>
      <ModalShell
        open={open}
        onClose={onClose}
        size="xl"
        zIndex={zIndex}
        title={`Edit teaching plan #${plan.id}`}
        subtitle={`${meta?.yearLabel || (meta?.academicYearId ? `AY #${meta.academicYearId}` : '')}${
          semester != null ? ` · Semester ${semester}` : ''
        } · Save checks facility & group conflicts`}
        footer={
          <>
            {canDelete ? (
              <button
                type="button"
                disabled={saving || deleting}
                onClick={remove}
                className="mr-auto text-sm font-medium text-red-700 hover:underline disabled:opacity-50"
              >
                {deleting ? 'Deleting…' : 'Delete plan'}
              </button>
            ) : (
              <span className="mr-auto" />
            )}
            <ModalSecondaryButton onClick={onClose} disabled={saving || deleting}>
              Cancel
            </ModalSecondaryButton>
            <ModalPrimaryButton
              disabled={saving || deleting || !moduleId || !facilityId || !selectedGroupIds.length}
              onClick={save}
            >
              {saving ? 'Saving…' : 'Save changes'}
            </ModalPrimaryButton>
          </>
        }
        bodyClassName="px-4 sm:px-5 py-4 space-y-4"
      >
          {loadingMeta && <p className="m-0 text-xs text-slate-400">Loading modules, facilities…</p>}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <PickerButton
              label="Module"
              kind="module"
              placeholder="Search module…"
              valueLabel={moduleLabel}
              onClick={() => setPicker({ type: 'module' })}
            />
            <div>
              <label className="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-1">
                Status
              </label>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25"
              >
                <option value="pending">Pending</option>
                <option value="Approved">Approved</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <PickerButton
              label="Leader lecturer"
              kind="lecturer"
              optional
              placeholder="Search lecturer…"
              valueLabel={leaderLabel}
              onClick={() => setPicker({ type: 'leader' })}
            />
            <PickerButton
              label="Other lecturers"
              kind="lecturer"
              optional
              placeholder="Search & multi-select…"
              valueLabel={othersLabel}
              onClick={() => setPicker({ type: 'others' })}
            />
          </div>

          <div>
            <PickerButton
              label="Facility"
              kind="facility"
              placeholder="Search facility…"
              valueLabel={facilityLabel}
              onClick={() => setPicker({ type: 'facility' })}
            />
            <p className="m-0 mt-1 text-[11px] text-slate-400">
              {availableFacilities.length
                ? `${availableFacilities.length} free for these sessions (excluding this plan)`
                : 'Showing all facilities'}
            </p>
          </div>

          <div>
            <div className="flex items-center justify-between gap-2 mb-1.5">
              <label className="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold m-0">
                Groups (required)
              </label>
              <div className="flex gap-2 text-[10px]">
                <button
                  type="button"
                  className="text-[#00628b] font-semibold underline"
                  onClick={() => setSelectedGroupIds(allGroups.map((g) => Number(g.id)))}
                >
                  Select all
                </button>
                <button type="button" className="text-slate-500 underline" onClick={() => setSelectedGroupIds([])}>
                  Clear
                </button>
              </div>
            </div>
            <div className="flex flex-wrap gap-2 max-h-36 overflow-y-auto border border-slate-100 rounded-xl p-2.5 bg-[#fbfcfe]">
              {allGroups.length === 0 ? (
                <span className="text-xs text-slate-400 italic">No groups loaded</span>
              ) : (
                allGroups.map((g) => {
                  const on = selectedGroupIds.map(Number).includes(Number(g.id));
                  return (
                    <button
                      key={g.id}
                      type="button"
                      onClick={() => toggleGroup(g.id)}
                      className={`px-2.5 py-1 rounded-lg text-[11px] border font-semibold transition ${
                        on
                          ? 'bg-[#c45c26] text-white border-[#c45c26]'
                          : 'bg-white text-slate-700 border-amber-300 hover:border-[#c45c26]/50'
                      }`}
                      title={[g.programName, g.yearOfStudy != null ? `Y${g.yearOfStudy}` : '', g.campusName]
                        .filter(Boolean)
                        .join(' · ')}
                    >
                      {g.name} ({g.size || 0})
                      {on ? ' ✓' : ''}
                    </button>
                  );
                })
              )}
            </div>
            {selectedGroupIds.length === 0 && (
              <p className="m-0 mt-1 text-[11px] text-amber-700">Select at least one group.</p>
            )}
          </div>

          <div>
            <h4 className="m-0 mb-2 text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Sessions</h4>
            <div className="space-y-2">
              {sessions.map((s, idx) => (
                <div key={idx} className="grid grid-cols-[1fr_1fr_1fr_auto] gap-2">
                  <select
                    value={s.day}
                    onChange={(e) =>
                      setSessions((prev) => prev.map((row, i) => (i === idx ? { ...row, day: e.target.value } : row)))
                    }
                    className="rounded-xl border border-slate-200 px-2 py-2 text-sm"
                  >
                    {days.map((d) => (
                      <option key={d} value={d}>
                        {d}
                      </option>
                    ))}
                  </select>
                  <input
                    type="time"
                    value={s.start}
                    onChange={(e) =>
                      setSessions((prev) =>
                        prev.map((row, i) => (i === idx ? { ...row, start: e.target.value } : row))
                      )
                    }
                    className="rounded-xl border border-slate-200 px-2 py-2 text-sm"
                  />
                  <input
                    type="time"
                    value={s.end}
                    onChange={(e) =>
                      setSessions((prev) => prev.map((row, i) => (i === idx ? { ...row, end: e.target.value } : row)))
                    }
                    className="rounded-xl border border-slate-200 px-2 py-2 text-sm"
                  />
                  {sessions.length > 1 && (
                    <button
                      type="button"
                      className="p-2 text-red-600"
                      onClick={() => setSessions((p) => p.filter((_, i) => i !== idx))}
                    >
                      <Trash2 size={14} />
                    </button>
                  )}
                </div>
              ))}
            </div>
            <div className="flex flex-wrap gap-2 mt-2">
              {timeBlocks.map((b) => (
                <button
                  key={b.id}
                  type="button"
                  className="text-xs px-2.5 py-1 rounded-lg border border-slate-200 hover:bg-[#fff8f2] hover:border-[#c45c26]/40"
                  onClick={() =>
                    setSessions((prev) => [
                      ...prev,
                      { day: prev[0]?.day || 'Monday', start: b.start, end: b.end },
                    ])
                  }
                >
                  + {b.label}
                </button>
              ))}
              <button
                type="button"
                className="text-xs px-2.5 py-1 rounded-lg border border-slate-200"
                onClick={() => setSessions((prev) => [...prev, { day: 'Monday', start: '08:00', end: '13:00' }])}
              >
                <Plus size={12} className="inline" /> Custom
              </button>
            </div>
          </div>

          {conflicts && (
            <ConflictBoxWithPlanViewer
              conflicts={conflicts}
              title="Cannot save — facility or group conflict"
              meta={meta}
              canDelete={canDelete}
              showError={showError}
              showSuccess={showSuccess}
              showWarning={showWarning}
              zIndex={zIndex + 25}
              EditModal={EditTeachingPlanModal}
              onPlanChanged={() => {
                // Conflicting plan was edited/deleted — clear stale conflict; user can retry save
                setConflicts(null);
              }}
            />
          )}
      </ModalShell>

      <SearchablePicker
        open={picker?.type === 'module'}
        onClose={() => setPicker(null)}
        kind="module"
        title="Choose module"
        placeholder="Search module code or name…"
        items={modules}
        value={moduleId}
        getLabel={(m) => `${m.code ? `${m.code} — ` : ''}${m.name}`}
        getMeta={(m) => `Year ${m.year ?? '—'} · Sem ${m.semester ?? '—'}`}
        onSelect={(m) => setModuleId(String(m.id))}
      />
      <SearchablePicker
        open={picker?.type === 'leader'}
        onClose={() => setPicker(null)}
        kind="lecturer"
        title="Leader lecturer"
        subtitle="Optional — pick none to clear"
        placeholder="Search name or email…"
        items={[{ id: '', names: '— None —' }, ...lecturers]}
        value={leaderId || ''}
        getLabel={(u) => u.names}
        getMeta={(u) => u.urEmail || u.email || ''}
        onSelect={(u) => setLeaderId(u.id ? String(u.id) : '')}
      />
      <SearchablePicker
        open={picker?.type === 'others'}
        onClose={() => setPicker(null)}
        kind="lecturer"
        accent="amber"
        title="Other lecturers"
        subtitle="Multi-select · amber chips show who is assigned"
        placeholder="Search lecturer name or email…"
        items={lecturers.filter((u) => String(u.id) !== String(leaderId))}
        value={otherLecturerIds}
        multiple
        getLabel={(u) => u.names}
        getMeta={(u) => u.urEmail || u.email || ''}
        onSelect={(list) => setOtherLecturerIds(list.map((u) => u.id))}
        confirmLabel="Apply lecturers"
      />
      <SearchablePicker
        open={picker?.type === 'facility'}
        onClose={() => setPicker(null)}
        kind="facility"
        title="Choose facility"
        placeholder="Search room name or campus…"
        items={facilityList}
        value={facilityId}
        getLabel={(f) => f.name}
        getMeta={(f) =>
          `${f.capacity ? `Cap ${f.capacity}` : ''}${f.campus?.name ? ` · ${f.campus.name}` : ''}`
        }
        onSelect={(f) => setFacilityId(String(f.id))}
      />
    </>
  );
}
