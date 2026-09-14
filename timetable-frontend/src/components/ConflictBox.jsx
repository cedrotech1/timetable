import { useState } from 'react';
import { AlertTriangle, Eye, Loader2 } from 'lucide-react';
import { timetableService } from '../services/api';

function fmtConflictTime(t) {
  const s = String(t || '').slice(0, 5);
  return s || '—';
}

export function summarizeConflictEntry(r) {
  const when = `${r.day || ''} ${fmtConflictTime(r.startTime || r.start_time)}–${fmtConflictTime(
    r.endTime || r.end_time
  )}`.trim();
  const mod =
    [r.moduleCode || r.module_code, r.moduleName || r.module_name].filter(Boolean).join(' — ') ||
    'another class';
  const fac = r.facilityName || r.facility_name || '';
  const grps = r.groupsLabel || r.groups_label || r.groupName || r.group_name || '';
  const where = r.source === 'batch' ? 'another Excel row in this import' : 'already saved in the system';
  return { when, mod, fac, grps, where, reason: r.simpleReason || r.simple_reason || null };
}

function conflictPlanId(r) {
  const id = r?.timetableId ?? r?.timetable_id ?? null;
  if (id == null || id === '' || String(id).startsWith('temp')) return null;
  const n = Number(id);
  return Number.isFinite(n) && n > 0 ? n : null;
}

function ViewConflictButton({ entry, onView, loadingId }) {
  const id = conflictPlanId(entry);
  if (!id || typeof onView !== 'function') return null;
  const loading = Number(loadingId) === Number(id);
  return (
    <button
      type="button"
      disabled={loading}
      onClick={(e) => {
        e.preventDefault();
        e.stopPropagation();
        onView(id, entry);
      }}
      className="inline-flex items-center gap-1 shrink-0 rounded-md border border-[#00628b]/30 bg-white px-2 py-0.5 text-[10px] font-semibold text-[#00628b] hover:bg-[#e8f4f8] disabled:opacity-60"
      title={`View teaching plan #${id}`}
    >
      {loading ? <Loader2 size={11} className="animate-spin" /> : <Eye size={11} />}
      View
    </button>
  );
}

/**
 * Conflict list with View on each saved teaching-plan conflict.
 * onViewPlan(timetableId, entry) — parent opens edit modal like General timetable.
 */
export default function ConflictBox({
  conflicts,
  title = 'Cannot save — facility or group conflict',
  onViewPlan,
  loadingId = null,
}) {
  if (!conflicts) return null;
  const facility = conflicts.facility || [];
  const groups = conflicts.groups || {};
  const groupEntries = Object.entries(groups);
  if (!facility.length && !groupEntries.length) return null;

  return (
    <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-950">
      <div className="flex items-center gap-2 font-semibold mb-2">
        <AlertTriangle size={16} className="text-red-600 shrink-0" />
        <span>{title}</span>
      </div>
      <p className="m-0 mb-2 text-[11px] text-red-800/80">
        Click <strong>View</strong> to open that teaching plan (same as a General timetable row).
      </p>

      {facility.length > 0 && (
        <div className="mb-3">
          <p className="m-0 mb-1.5 font-semibold text-xs">Facility / room ({facility.length})</p>
          <ul className="m-0 p-0 list-none space-y-1.5">
            {facility.slice(0, 12).map((r, i) => {
              const s = summarizeConflictEntry(r);
              const id = conflictPlanId(r);
              return (
                <li
                  key={`f-${id || i}-${s.when}`}
                  className="flex flex-wrap items-start justify-between gap-2 rounded-lg border border-red-100 bg-white/70 px-2.5 py-2"
                >
                  <div className="min-w-0 flex-1 text-xs leading-snug">
                    <strong>{s.when}</strong>: {s.mod}
                    {s.fac ? (
                      <>
                        {' '}
                        in <strong>{s.fac}</strong>
                      </>
                    ) : null}
                    {s.grps ? <> ({s.grps})</> : null}
                    {id ? <span className="text-red-700/60"> · Plan #{id}</span> : null}
                    <span className="text-red-700/70"> — {s.where}</span>
                    {s.reason ? <div className="text-red-800/80 mt-0.5">{s.reason}</div> : null}
                  </div>
                  <ViewConflictButton entry={r} onView={onViewPlan} loadingId={loadingId} />
                </li>
              );
            })}
            {facility.length > 12 && (
              <li className="text-xs text-red-700/70 px-1">…and {facility.length - 12} more</li>
            )}
          </ul>
        </div>
      )}

      {groupEntries.length > 0 && (
        <div>
          <p className="m-0 mb-1.5 font-semibold text-xs">
            Group time overlaps ({groupEntries.length} group(s))
          </p>
          <ul className="m-0 p-0 list-none space-y-2">
            {groupEntries.slice(0, 8).map(([gid, rows]) => (
              <li key={gid} className="rounded-lg border border-red-100 bg-white/70 px-2.5 py-2">
                <strong className="text-xs">
                  {rows[0]?.groupName || rows[0]?.group_name || `Group #${gid}`}
                </strong>
                <ul className="m-0 mt-1.5 p-0 list-none space-y-1.5">
                  {(rows || []).slice(0, 6).map((r, i) => {
                    const s = summarizeConflictEntry(r);
                    const id = conflictPlanId(r);
                    return (
                      <li
                        key={`g-${gid}-${id || i}-${s.when}`}
                        className="flex flex-wrap items-start justify-between gap-2 text-xs text-red-900"
                      >
                        <div className="min-w-0 flex-1 leading-snug">
                          <strong>{s.when}</strong>: {s.mod}
                          {s.fac ? ` · ${s.fac}` : ''}
                          {id ? <span className="text-red-700/60"> · Plan #{id}</span> : null}
                          <span className="text-red-700/70"> — {s.where}</span>
                        </div>
                        <ViewConflictButton entry={r} onView={onViewPlan} loadingId={loadingId} />
                      </li>
                    );
                  })}
                </ul>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

/**
 * ConflictBox + loads plan by id and renders EditTeachingPlanModal (lazy circular-safe).
 */
export function ConflictBoxWithPlanViewer({
  conflicts,
  title,
  meta = null,
  canDelete = true,
  showError,
  showSuccess,
  showWarning,
  onPlanChanged,
  EditModal,
  zIndex = 90,
}) {
  const [viewPlan, setViewPlan] = useState(null);
  const [loadingId, setLoadingId] = useState(null);

  const handleView = async (id) => {
    try {
      setLoadingId(id);
      const res = await timetableService.getById(id);
      const plan = res?.data;
      if (!plan) {
        showError?.('Teaching plan not found');
        return;
      }
      setViewPlan(plan);
    } catch (error) {
      showError?.(error.response?.data?.message || 'Failed to open conflicting plan');
    } finally {
      setLoadingId(null);
    }
  };

  if (!EditModal) {
    return <ConflictBox conflicts={conflicts} title={title} onViewPlan={handleView} loadingId={loadingId} />;
  }

  return (
    <>
      <ConflictBox conflicts={conflicts} title={title} onViewPlan={handleView} loadingId={loadingId} />
      <EditModal
        open={Boolean(viewPlan)}
        plan={viewPlan}
        meta={
          meta || {
            academicYearId: viewPlan?.academicYear?.id || viewPlan?.academicYearId,
            yearLabel: viewPlan?.academicYear?.yearLabel,
            semester: viewPlan?.semester,
          }
        }
        canDelete={canDelete}
        zIndex={zIndex}
        onClose={() => setViewPlan(null)}
        onSaved={() => {
          setViewPlan(null);
          onPlanChanged?.();
        }}
        onDeleted={() => {
          setViewPlan(null);
          onPlanChanged?.();
        }}
        showError={showError}
        showSuccess={showSuccess}
        showWarning={showWarning}
      />
    </>
  );
}
