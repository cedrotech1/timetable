import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import * as XLSX from 'xlsx';
import {
  CalendarDays,
  Layers,
  Upload,
  Plus,
  Trash2,
  AlertTriangle,
  CheckCircle2,
  Building2,
  Users,
  Clock,
  BookOpen,
  Loader2,
  FileSpreadsheet,
  Download,
  MapPin,
} from 'lucide-react';
import {
  timetableService,
  modulesService,
  facilitiesService,
  campusesService,
  programsService,
} from '../services/api';
import { useNotification } from '../contexts/NotificationContext';
import { useAuth } from '../contexts/AuthContext';
import { canManageOrg } from '../utils/roles';
import { appPath, publicAssetUrl } from '../utils/appPaths';
import { parseTimetableWorkbook } from '../utils/excelTimetableParser';
import SearchablePicker, { PickerButton } from '../components/SearchablePicker';
import { pendingConflictsStore } from '../utils/pendingConflictsStore';
import { ConflictBoxWithPlanViewer, conflictKindsLabel, getConflictKindsFromPayload } from '../components/ConflictBox';
import EditTeachingPlanModal from '../components/EditTeachingPlanModal';
import {
  capitalizePersonName,
  capitalizeCampusName,
  facilityCompactLabel,
  facilityPickerLabel,
  facilityPickerMeta,
  facilitySearchHaystack,
  lecturerPickerLabel,
  lecturerPickerMeta,
} from '../utils/formatDisplay';
import { fmtTime, toMinutes, sessionsOverlap } from '../utils/timeFormat';
import { exportUploadSectionsPdf } from '../utils/exportUploadPreviewPdf';

const MODES = [
  { id: 'single', label: 'Single entry', icon: CalendarDays, blurb: 'One teaching plan at a time' },
  { id: 'bulk', label: 'Bulk entry', icon: Layers, blurb: 'Many rows sharing the same groups' },
  { id: 'upload', label: 'Excel upload', icon: Upload, blurb: 'Import spreadsheet then save via the same bulk engine' },
];

const emptyBulkRow = () => ({
  moduleId: '',
  day: 'Monday',
  start: '08:00',
  end: '13:00',
  facilityId: '',
  leaderLecturerId: '',
  otherLecturerIds: [],
});

function fmtConflictTime(t) {
  return fmtTime(t);
}

function rowStudentNeed(row) {
  return (row?.groups || []).reduce((sum, g) => sum + (Number(g.size) || 0), 0);
}

function quietRowWarnings(warnings = []) {
  return (warnings || []).filter(
    (w) =>
      !/Auto room:|auto-assign|free facility|No free facility|weak match|Shared room|Created module from Excel/i.test(
        String(w || '')
      )
  );
}

function sanitizeMatchedSections(sections = []) {
  return (sections || []).map((sec) => ({
    ...sec,
    rows: (sec.rows || []).map((row) => ({
      ...row,
      warnings: quietRowWarnings(row.warnings),
    })),
  }));
}

function UploadResultPanel({ uploadResult, onJumpRow, conflictViewerProps }) {
  if (!uploadResult) return null;
  const results = uploadResult.results || [];
  const failed = results.filter((r) => !r.success);
  const ok = results.filter((r) => r.success);
  const savedCount = uploadResult.saved ?? ok.length;
  const failedCount = uploadResult.failed ?? failed.length;

  return (
    <section className="bg-white rounded-xl border border-red-100 p-4 text-sm space-y-3 shadow-sm">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
          <p className="m-0 font-semibold text-gray-900">Last result</p>
          <p className="m-0 mt-1 text-gray-600">
            {uploadResult.dryRun
              ? `Checked OK: ${ok.length} teaching plan(s) · Failed: ${failedCount} (dry run — nothing written)`
              : `Teaching plans saved: ${savedCount} · Failed: ${failedCount}`}
          </p>
          {failedCount > 0 && (
            <p className="m-0 mt-1 text-[11px] text-amber-800">
              These numbers are teaching plans (module + room + groups + time), not “facilities saved”.
              Failed rows usually have a GROUP conflict (same group already booked) or a ROOM conflict
              (room already taken). Fix those rows, then re-check / save.
            </p>
          )}
        </div>
        {failedCount > 0 && (
          <span className="inline-flex items-center gap-1 text-red-700 text-xs font-semibold">
            <AlertTriangle size={14} /> Fix conflicts below, then re-check / save
          </span>
        )}
      </div>

      {failedCount === 0 ? (
        <p className="m-0 text-emerald-700 text-sm">No facility/group conflicts on checked rows.</p>
      ) : (
        <div className="max-h-[28rem] overflow-y-auto space-y-3">
          {failed.map((r) => {
            const a = r.attempt || {};
            const when = `${a.day || ''} ${fmtConflictTime(a.start)}–${fmtConflictTime(a.end)}`.trim();
            const mod = [a.moduleCode, a.moduleName].filter(Boolean).join(' — ') || '—';
            const kinds = getConflictKindsFromPayload(r.conflicts, r.conflictKinds);
            const kindLabel = r.code === 'CONFLICT' ? conflictKindsLabel(kinds) : r.code || 'error';
            return (
              <div key={r.index} className="rounded-lg border border-red-200 bg-red-50/60 p-3">
                <div className="flex flex-wrap items-start justify-between gap-2 mb-2">
                  <div>
                    <p className="m-0 font-semibold text-red-900">
                      Row #{r.index + 1} — {kindLabel}
                    </p>
                    <p className="m-0 mt-1 text-xs text-gray-700">
                      <strong>{when || '—'}</strong> · {mod}
                      {a.facilityName ? ` · Room: ${a.facilityName}` : ''}
                      {a.groupsLabel ? ` · Groups: ${a.groupsLabel}` : ''}
                    </p>
                    {r.message && <p className="m-0 mt-1 text-xs text-red-800 font-medium">{r.message}</p>}
                  </div>
                  {typeof onJumpRow === 'function' && (
                    <button
                      type="button"
                      className="text-xs text-[#00628b] underline"
                      onClick={() => onJumpRow(r.index)}
                    >
                      Show in table
                    </button>
                  )}
                </div>
                <ConflictBoxWithPlanViewer
                  conflicts={r.conflicts}
                  conflictKinds={kinds}
                  title="Why it failed"
                  EditModal={EditTeachingPlanModal}
                  {...(conflictViewerProps || {})}
                />
              </div>
            );
          })}
        </div>
      )}
    </section>
  );
}

export default function SetTimetablePage() {
  const { user } = useAuth();
  const { showSuccess, showError, showWarning } = useNotification();
  const canManage = canManageOrg(user?.role);

  const [mode, setMode] = useState('single');
  const [settings, setSettings] = useState(null);
  const [intakes, setIntakes] = useState([]);
  const [modules, setModules] = useState([]);
  const [facilities, setFacilities] = useState([]);
  const [lecturers, setLecturers] = useState([]);
  const [programs, setPrograms] = useState([]);
  const [campuses, setCampuses] = useState([]);
  const [availableFacilities, setAvailableFacilities] = useState([]);
  const [uploadFacilityChoices, setUploadFacilityChoices] = useState([]);
  const [uploadFacilityLoading, setUploadFacilityLoading] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [conflicts, setConflicts] = useState(null);

  // Shared selection
  const [selectedProgramId, setSelectedProgramId] = useState('');
  const [selectedIntakeId, setSelectedIntakeId] = useState('');
  const [selectedGroupIds, setSelectedGroupIds] = useState([]);

  // Single
  const [sessions, setSessions] = useState([{ day: 'Monday', start: '08:00', end: '13:00' }]);
  const [moduleId, setModuleId] = useState('');
  const [facilityId, setFacilityId] = useState('');
  const [leaderId, setLeaderId] = useState('');
  const [otherLecturerIds, setOtherLecturerIds] = useState([]);

  // Bulk
  const [bulkRows, setBulkRows] = useState([emptyBulkRow()]);

  // Upload (sample.xls style sections)
  const [parsedSections, setParsedSections] = useState([]);
  const [matchedSections, setMatchedSections] = useState([]);
  const [parseMeta, setParseMeta] = useState(null);
  const [uploadCampusId, setUploadCampusId] = useState('');
  const [uploadResult, setUploadResult] = useState(null);
  const [uploadRowMeta, setUploadRowMeta] = useState([]);
  const [activeSectionIdx, setActiveSectionIdx] = useState(0);
  const [facilityMode, setFacilityMode] = useState('excel'); // excel | auto
  const [picker, setPicker] = useState(null); // { type, rowIndex? }
  const [pendingItems, setPendingItems] = useState(() => pendingConflictsStore.list());
  const [showPending, setShowPending] = useState(false);
  const [uploadBusy, setUploadBusy] = useState(null); // { fileName, step, detail }
  const [uploadFileName, setUploadFileName] = useState('');
  const fileInputRef = useRef(null);

  const refreshPending = () => setPendingItems(pendingConflictsStore.list());

  const pushPendingFromResults = (results, modeName, rowPayloads = []) => {
    const failed = (results || []).filter((r) => !r.success);
    if (!failed.length) return;
    pendingConflictsStore.addMany(
      failed.map((r) => ({
        source: 'set-timetable',
        mode: modeName,
        message: r.message || 'Not saved',
        attempt: r.attempt || null,
        conflicts: r.conflicts || null,
        payload: rowPayloads[r.index] || null,
      }))
    );
    refreshPending();
    setShowPending(true);
  };

  const loadMeta = useCallback(async () => {
    try {
      setLoading(true);
      const [settingsRes, intakesRes, modulesRes, facilitiesRes, programsRes, campusesRes] =
        await Promise.all([
          timetableService.getSettings(),
          timetableService.listIntakes(),
          modulesService.getAll(),
          facilitiesService.getAll(),
          programsService.getAll(),
          campusesService.getAll(),
        ]);

      setSettings(settingsRes?.data || null);
      setIntakes(intakesRes?.data || []);
      setModules(modulesRes?.data || []);
      setFacilities(facilitiesRes?.data || []);
      setPrograms(programsRes?.data || []);
      setCampuses(campusesRes?.data || []);
      const camps = campusesRes?.data || [];
      const huye = camps.find((c) => /huye/i.test(c.name));
      if (huye) setUploadCampusId(String(huye.id));
      else if (camps[0]) setUploadCampusId(String(camps[0].id));

      try {
        const usersRes = await timetableService.listLecturers();
        setLecturers(usersRes?.data || []);
      } catch {
        setLecturers([]);
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Failed to load timetable setup data');
    } finally {
      setLoading(false);
    }
  }, [showError]);

  useEffect(() => {
    loadMeta();
  }, [loadMeta]);

  const days = settings?.days || ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
  const timeBlocks = settings?.timeBlocks || [];
  const academicYearId = settings?.settings?.academicYearId;
  const semester = settings?.settings?.semester;
  const conflictViewerProps = useMemo(
    () => ({
      meta: {
        academicYearId,
        semester,
        yearLabel: settings?.settings?.academicYear?.yearLabel || null,
      },
      canDelete: canManage,
      showError,
      showSuccess,
      showWarning,
    }),
    [academicYearId, semester, settings, canManage, showError, showSuccess, showWarning]
  );

  const programIntakes = useMemo(
    () => intakes.filter((i) => String(i.programId) === String(selectedProgramId)),
    [intakes, selectedProgramId]
  );

  const programSetupById = useMemo(() => {
    const map = new Map();
    for (const intake of intakes) {
      const pid = String(intake.programId);
      const groups = intake.groups || [];
      const prev = map.get(pid) || { intakes: 0, groups: 0, years: [] };
      prev.intakes += 1;
      prev.groups += groups.length;
      if (intake.yearOfStudy != null) prev.years.push(Number(intake.yearOfStudy));
      map.set(pid, prev);
    }
    return map;
  }, [intakes]);

  const programsForPicker = useMemo(() => {
    return [...programs].sort((a, b) => {
      const sa = programSetupById.get(String(a.id));
      const sb = programSetupById.get(String(b.id));
      const aReady = (sa?.groups || 0) > 0 ? 1 : 0;
      const bReady = (sb?.groups || 0) > 0 ? 1 : 0;
      if (aReady !== bReady) return bReady - aReady;
      return String(a.name || '').localeCompare(String(b.name || ''));
    });
  }, [programs, programSetupById]);

  const selectedIntake = intakes.find((i) => String(i.id) === String(selectedIntakeId));
  const intakeGroups = selectedIntake?.groups || [];
  const intakeGroupIdsKey = intakeGroups.map((g) => g.id).join(',');
  // Auto-select all groups once per intake (re-picking same value won't fire <select> onChange)
  const autoSelectedIntakeRef = useRef(null);

  useEffect(() => {
    if (!selectedIntakeId) {
      autoSelectedIntakeRef.current = null;
      return;
    }
    if (!intakeGroupIdsKey) return;
    if (autoSelectedIntakeRef.current === String(selectedIntakeId)) return;
    autoSelectedIntakeRef.current = String(selectedIntakeId);
    setSelectedGroupIds(intakeGroupIdsKey.split(',').map(Number));
  }, [selectedIntakeId, intakeGroupIdsKey]);

  const programModules = useMemo(() => {
    if (!selectedProgramId) return modules;
    return modules.filter((m) => String(m.programId) === String(selectedProgramId));
  }, [modules, selectedProgramId]);

  const toggleGroup = (id) => {
    const nid = Number(id);
    setSelectedGroupIds((prev) =>
      prev.map(Number).includes(nid) ? prev.filter((x) => Number(x) !== nid) : [...prev, nid]
    );
  };

  const refreshAvailableFacilities = async (sessionList, { minCapacity = 0 } = {}) => {
    if (!academicYearId || !semester || !sessionList?.length) {
      setAvailableFacilities([]);
      return [];
    }
    try {
      const res = await timetableService.availableFacilities({
        academicYearId,
        semester,
        sessions: sessionList,
        minCapacity: minCapacity || 0,
      });
      const list = res?.data || [];
      setAvailableFacilities(list);
      return list;
    } catch {
      setAvailableFacilities([]);
      return [];
    }
  };

  /** Facilities already claimed by other Excel rows at the same day/time (this import). */
  const facilitiesTakenInUpload = (sectionIndex, rowIndex) => {
    const taken = new Set();
    const target = matchedSections[sectionIndex]?.rows?.find((x) => x.rowIndex === rowIndex);
    if (!target) return taken;
    matchedSections.forEach((sec, si) => {
      (sec.rows || []).forEach((row) => {
        if (si === sectionIndex && row.rowIndex === rowIndex) return;
        if (row.mergedAway || row.status === 'skipped') return;
        if (!row.facility?.id) return;
        if (sessionsOverlap(target, row)) taken.add(Number(row.facility.id));
      });
    });
    return taken;
  };

  const openUploadFacilityPicker = async (sectionIndex, rowIndex) => {
    const row = matchedSections[sectionIndex]?.rows?.find((x) => x.rowIndex === rowIndex);
    setPicker({ type: 'upload-facility', sectionIndex, rowIndex });
    setUploadFacilityChoices([]);
    if (!row?.day || !row?.start || !row?.end) return;
    setUploadFacilityLoading(true);
    try {
      const need = rowStudentNeed(row);
      const res = await timetableService.availableFacilities({
        academicYearId,
        semester,
        sessions: [{ day: row.day, start: row.start, end: row.end }],
        minCapacity: need || 0,
      });
      const taken = facilitiesTakenInUpload(sectionIndex, rowIndex);
      const currentId = row.facility?.id ? Number(row.facility.id) : null;
      let list = (res?.data || []).filter((f) => {
        const id = Number(f.id);
        if (currentId && id === currentId) return true;
        return !taken.has(id);
      });
      // Keep current selection visible even if API no longer lists it
      if (currentId && !list.some((f) => Number(f.id) === currentId) && row.facility) {
        const fromAll = facilities.find((f) => Number(f.id) === currentId);
        list = [
          {
            id: row.facility.id,
            name: row.facility.name,
            capacity: row.facility.capacity,
            buildName: row.facility.buildName,
            buildCode: row.facility.buildCode,
            campus: row.facility.campus,
            ...(fromAll || {}),
          },
          ...list,
        ];
      }
      setUploadFacilityChoices(list);
    } catch {
      setUploadFacilityChoices([]);
    } finally {
      setUploadFacilityLoading(false);
    }
  };

  useEffect(() => {
    if (mode === 'single') refreshAvailableFacilities(sessions);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mode, sessions, academicYearId, semester]);

  const buildSinglePayload = () => ({
    moduleId: Number(moduleId),
    facilityId: Number(facilityId),
    leaderLecturerId: leaderId ? Number(leaderId) : null,
    otherLecturerIds,
    groupIds: selectedGroupIds,
    sessions,
    academicYearId,
    semester,
  });

  const saveSingle = async () => {
    setConflicts(null);
    try {
      setSaving(true);
      const payload = buildSinglePayload();
      const result = await timetableService.create(payload);
      if (result?.success) {
        showSuccess(`Timetable saved (#${result.data?.id})`);
        setFacilityId('');
        setModuleId('');
        setOtherLecturerIds([]);
      } else {
        showError(result?.message || 'Save failed');
      }
    } catch (error) {
      const data = error.response?.data;
      if (data?.status === 'conflict' || error.response?.status === 409) {
        setConflicts(data.conflicts);
        showWarning(data.message || 'Conflicts detected');
        const mod = modules.find((m) => String(m.id) === String(moduleId));
        const fac = facilities.find((f) => String(f.id) === String(facilityId));
        pendingConflictsStore.addMany([
          {
            mode: 'single',
            message: data.message || 'Conflict — not saved',
            conflicts: data.conflicts,
            attempt: {
              day: sessions[0]?.day,
              start: sessions[0]?.start,
              end: sessions[0]?.end,
              moduleCode: mod?.code,
              moduleName: mod?.name,
              facilityName: fac?.name,
            },
            payload: buildSinglePayload(),
          },
        ]);
        refreshPending();
        setShowPending(true);
      } else {
        showError(data?.message || error.message || 'Save failed');
      }
    } finally {
      setSaving(false);
    }
  };

  const saveBulk = async ({ dryRun = false } = {}) => {
    setConflicts(null);
    setUploadResult(null);
    try {
      setSaving(true);
      const rows = bulkRows.map((r) => ({
        moduleId: Number(r.moduleId),
        moduleCode: modules.find((m) => String(m.id) === String(r.moduleId))?.code,
        moduleName: modules.find((m) => String(m.id) === String(r.moduleId))?.name,
        facilityId: Number(r.facilityId),
        facilityName: facilities.find((f) => String(f.id) === String(r.facilityId))?.name,
        leaderLecturerId: r.leaderLecturerId ? Number(r.leaderLecturerId) : null,
        otherLecturerIds: (r.otherLecturerIds || []).map(Number),
        day: r.day,
        start: r.start,
        end: r.end,
        groupIds: selectedGroupIds,
      }));
      const result = await timetableService.bulk({
        academicYearId,
        semester,
        groupIds: selectedGroupIds,
        rows,
        dryRun,
      });
      if (result?.success) {
        const failed = result.data?.failed || 0;
        const saved = result.data?.saved || 0;
        if (dryRun) {
          showSuccess(`Dry run: ${result.data?.results?.length || 0} rows checked (${failed} with issues)`);
        } else {
          showSuccess(`Bulk save: ${saved} saved, ${failed} failed`);
        }
        setUploadResult(result.data);
        const conflictRow = (result.data?.results || []).find((r) => r.code === 'CONFLICT');
        if (conflictRow) setConflicts(conflictRow.conflicts);
        if (!dryRun && failed > 0) {
          pushPendingFromResults(result.data.results, 'bulk', rows);
        } else if (dryRun && failed > 0) {
          pushPendingFromResults(result.data.results, 'bulk-check', rows);
        }
      } else {
        showError(result?.message || 'Bulk save failed');
      }
    } catch (error) {
      showError(error.response?.data?.message || error.message || 'Bulk save failed');
    } finally {
      setSaving(false);
    }
  };

  const handleExcel = async (file) => {
    if (!file) return;
    setParsedSections([]);
    setMatchedSections([]);
    setUploadResult(null);
    setParseMeta(null);
    setUploadFileName(file.name);
    setUploadBusy({
      fileName: file.name,
      step: 'read',
      detail: 'Reading spreadsheet…',
    });
    try {
      const buffer = await file.arrayBuffer();
      setUploadBusy({
        fileName: file.name,
        step: 'parse',
        detail: 'Parsing Year / Program / Group sections…',
      });
      // Yield so the loading UI can paint before heavy sync parse
      await new Promise((r) => setTimeout(r, 40));
      const { sections, meta } = parseTimetableWorkbook(XLSX, buffer);
      if (!sections.length) {
        showError('No Year/Group sections with rows found in this file');
        setUploadBusy(null);
        return;
      }
      setParsedSections(sections);
      setParseMeta(meta);
      setActiveSectionIdx(0);

      setUploadBusy({
        fileName: file.name,
        step: 'match',
        detail: `Matching ${sections.length} section(s) to programs, modules & rooms…`,
      });

      const campusId = uploadCampusId ? Number(uploadCampusId) : null;
      const matchRes = await timetableService.matchImport({
        sections,
        campusId,
        semester,
        academicYearId,
        facilityMode,
      });
      let matched = sanitizeMatchedSections(matchRes?.data?.sections || []);
      setMatchedSections(matched);

      const needCreate = matched.filter(
        (s) => s.program?.id && s.year && (s.groupNumbers || []).length && s.needsGroups
      );

      let autoAssign = matchRes?.data?.autoAssign;

      if (needCreate.length && campusId) {
        setUploadBusy({
          fileName: file.name,
          step: 'groups',
          detail: `Creating ${needCreate.length} missing intake(s) / group(s)…`,
        });
        const createRes = await timetableService.createIntakesFromImport({
          campusId,
          items: needCreate.map((s) => ({
            programId: s.program.id,
            yearOfStudy: s.year,
            campusId,
            groupNumbers: s.groupNumbers,
            sizeEach: s.sizeHint?.size || 40,
            sizeMode: s.sizeHint?.mode || 'total',
          })),
        });
        showSuccess(createRes?.message || 'Missing intakes & groups created');

        setUploadBusy({
          fileName: file.name,
          step: 'rematch',
          detail: 'Rematching after creating intakes & groups…',
        });
        const rematchRes = await timetableService.matchImport({
          sections,
          campusId,
          semester,
          academicYearId,
          facilityMode,
        });
        matched = sanitizeMatchedSections(rematchRes?.data?.sections || []);
        autoAssign = rematchRes?.data?.autoAssign || autoAssign;
        setMatchedSections(matched);
        await loadMeta();
      }

      if (facilityMode === 'auto' && autoAssign) {
        setUploadBusy({
          fileName: file.name,
          step: 'facilities',
          detail: `Auto facilities: ${autoAssign.assigned} assigned…`,
        });
        showSuccess(
          `Auto facilities: ${autoAssign.assigned} assigned, ${autoAssign.failed} failed`
        );
      }

      const stillNeed = matched.filter((s) => s.needsGroups || !s.program).length;
      showSuccess(
        `Parsed ${sections.length} section(s). ${
          stillNeed
            ? `${stillNeed} still need program/groups — pick program or create groups.`
            : 'Programs & groups matched.'
        }`
      );
    } catch (error) {
      showError(error.response?.data?.message || error.message || 'Failed to parse / match Excel');
    } finally {
      setUploadBusy(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  const rematchSections = async (sectionsOverride = null) => {
    const sections = sectionsOverride || parsedSections;
    if (!sections.length) return;
    try {
      setSaving(true);
      const matchRes = await timetableService.matchImport({
        sections,
        campusId: uploadCampusId ? Number(uploadCampusId) : null,
        semester,
        academicYearId,
        facilityMode,
      });
      setMatchedSections(sanitizeMatchedSections(matchRes?.data?.sections || []));
      if (facilityMode === 'auto' && matchRes?.data?.autoAssign) {
        showSuccess(
          `Rematched + auto facilities: ${matchRes.data.autoAssign.assigned} assigned`
        );
      } else {
        const c = matchRes?.data?.conflictPreview?.conflicts;
        showSuccess(
          c
            ? `Rematched — ${c} ROOM/GROUP conflict(s) flagged`
            : 'Rematched against system programs / intakes / groups'
        );
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Rematch failed');
    } finally {
      setSaving(false);
    }
  };

  const runAutoFacilities = async () => {
    if (!matchedSections.length) return;
    try {
      setSaving(true);
      const res = await timetableService.autoAssignFacilities({
        sections: matchedSections,
        academicYearId,
        semester,
        campusId: uploadCampusId ? Number(uploadCampusId) : null,
      });
      setMatchedSections(sanitizeMatchedSections(res?.data?.sections || []));
      showSuccess(res?.message || 'Facilities auto-assigned');
    } catch (error) {
      showError(error.response?.data?.message || 'Auto-assign failed');
    } finally {
      setSaving(false);
    }
  };

  const patchMatchedRow = (sectionIndex, rowKey, patch) => {
    setMatchedSections((prev) =>
      prev.map((sec, si) => {
        if (si !== sectionIndex) return sec;
        return {
          ...sec,
          rows: (sec.rows || []).map((row, ri) => {
            const matches =
              row.rowIndex === rowKey || ri === rowKey || String(row.rowIndex) === String(rowKey);
            if (!matches) return row;
            const next = { ...row, ...patch };
            if (patch.facility !== undefined) {
              next.facilityUserPicked = Boolean(patch.facility);
              next.saveConflict = null;
              next.warnings = quietRowWarnings(
                (next.warnings || []).filter((w) => !/facility|classroom|No free|ROOM/i.test(w))
              );
            }
            if (patch.lecturers !== undefined) {
              // Keep unresolved Excel names unless caller cleared them
              if (patch.missedLecturers !== undefined) {
                next.missedLecturers = patch.missedLecturers;
              }
              next.warnings = (next.warnings || []).filter((w) => !/lecturer/i.test(w));
            }
            if (next.module?.id && next.facility?.id && (next.groups || []).length) {
              next.status = (next.warnings || []).length ? 'warning' : 'ok';
            }
            return next;
          }),
        };
      })
    );
  };

  const createMissingGroups = async () => {
    if (!uploadCampusId) {
      showError('Select a default campus for creating intakes/groups');
      return;
    }

    // Create ALL section promotions at once (merge Group 1&2 + 3&4 + 5&6 + 7 into one intake)
    const items = matchedSections
      .filter((s) => s.program?.id && s.year && (s.groupNumbers || []).length)
      .map((s) => ({
        programId: s.program.id,
        yearOfStudy: s.year,
        campusId: Number(uploadCampusId),
        groupNumbers: s.groupNumbers,
        sizeEach: s.sizeHint?.size || 40,
        sizeMode: s.sizeHint?.mode || 'total',
      }));

    if (!items.length) {
      showError('No sections with matched program + year + group numbers. Fix program match first.');
      return;
    }

    try {
      setSaving(true);
      const res = await timetableService.createIntakesFromImport({
        campusId: Number(uploadCampusId),
        items,
      });
      showSuccess(res?.message || 'All promotions & groups saved');
      await rematchSections();
      await loadMeta();
    } catch (error) {
      showError(error.response?.data?.message || 'Failed to create intakes/groups');
    } finally {
      setSaving(false);
    }
  };

  const setForcedProgram = (sectionIndex, programId, { allSections = false } = {}) => {
    const pid = programId ? Number(programId) : undefined;
    const next = parsedSections.map((s, i) => {
      if (!allSections && i !== sectionIndex) return s;
      return { ...s, forced_program_id: pid };
    });
    setParsedSections(next);
    rematchSections(next);
  };

  const saveUpload = async ({ dryRun = false, sectionIndex = null } = {}) => {
    setConflicts(null);
    const rows = [];
    const rowMeta = []; // map bulk index → section/row for UI
    const sectionFilter = sectionIndex == null ? null : Number(sectionIndex);
    for (let si = 0; si < matchedSections.length; si += 1) {
      if (sectionFilter != null && si !== sectionFilter) continue;
      const sec = matchedSections[si];
      for (let ri = 0; ri < (sec.rows || []).length; ri += 1) {
        const row = sec.rows[ri];
        if (!row.module?.id || !(row.groups || []).length) continue;
        if (facilityMode !== 'skip' && !row.facility?.id) continue;
        if (row.status === 'skipped' || row.mergedAway) continue;
        // Include previous conflict rows so re-check / save works after facility fix
        const groupNames = row.groups.map((g) => g.name).filter(Boolean);
        const groupNameById = {};
        row.groups.forEach((g) => {
          if (g?.id) groupNameById[g.id] = g.name;
        });
        rows.push({
          moduleId: row.module.id,
          moduleCode: row.excel?.moduleCode || row.module.code,
          moduleName: row.excel?.moduleName || row.module.name,
          credits: row.excel?.credits ?? row.module?.credits ?? null,
          facilityId: row.facility?.id || null,
          facilityName: row.facility?.name || null,
          skipFacility: facilityMode === 'skip' && !row.facility?.id,
          leaderLecturerId: row.lecturers?.leader?.id || null,
          otherLecturerIds: (row.lecturers?.others || []).map((l) => l.id),
          day: row.day,
          start: row.start,
          end: row.end,
          groupIds: row.groups.map((g) => g.id),
          groupNames,
          groupNameById,
        });
        rowMeta.push({ sectionIndex: si, rowIndex: row.rowIndex, arrayIndex: ri });
      }
    }
    if (!rows.length) {
      showError(
        sectionFilter != null
          ? `This section has no fully matched rows to save (need module + groups${facilityMode === 'skip' ? '' : ' + facility'})`
          : `No fully matched rows to save (need module + groups${facilityMode === 'skip' ? '' : ' + facility'})`
      );
      return;
    }
    try {
      setSaving(true);
      const result = await timetableService.bulk({
        academicYearId,
        semester,
        rows: rows.map((r) => ({
          ...r,
          campusId: uploadCampusId ? Number(uploadCampusId) : null,
        })),
        dryRun,
      });
      const data = result?.data || null;
      setUploadResult(data);
      setUploadRowMeta(rowMeta);

      // Stamp save_conflict onto matched rows (PHP applyConflictCheckResults)
      if (data?.results?.length) {
        setMatchedSections((prev) => {
          const next = prev.map((sec, si) => {
            if (sectionFilter != null && si !== sectionFilter) return sec;
            return {
              ...sec,
              rows: (sec.rows || []).map((row) => ({ ...row, saveConflict: null })),
            };
          });
          data.results.forEach((r) => {
            const meta = rowMeta[r.index];
            if (!meta) return;
            const row = next[meta.sectionIndex]?.rows?.[meta.arrayIndex];
            if (!row) return;
            if (!r.success && r.conflicts) {
              row.saveConflict = r.conflicts;
              row.status = 'error';
            } else if (r.success) {
              row.saveConflict = null;
              const quiet = quietRowWarnings(row.warnings);
              row.warnings = quiet;
              if (row.status === 'error') row.status = quiet.length ? 'warning' : 'ok';
            }
          });
          return next;
        });

        const firstConflict = data.results.find((r) => !r.success && r.conflicts);
        if (firstConflict) {
          setConflicts(firstConflict.conflicts);
          const meta = rowMeta[firstConflict.index];
          if (meta) setActiveSectionIdx(meta.sectionIndex);
        }
      }

      if (result?.success) {
        const failed = data?.failed || 0;
        const scope =
          sectionFilter != null
            ? `this section (${matchedSections[sectionFilter]?.title || `§${sectionFilter + 1}`})`
            : 'all sections';
        if (failed > 0) {
          showWarning(
            dryRun
              ? `Dry run (${scope}): ${failed} teaching plan(s) failed of ${data?.results?.length || 0}`
              : `Teaching plans saved: ${data?.saved || 0} · Failed: ${failed} (${scope}) — not facilities`
          );
          pushPendingFromResults(data.results, dryRun ? 'upload-check' : 'upload', rows);
        } else {
          showSuccess(
            dryRun
              ? `Dry run OK (${scope}): ${data?.results?.length || 0} teaching plan(s), no conflicts`
              : `Saved ${data?.saved || 0} teaching plan(s) (${scope})`
          );
        }
      }
    } catch (error) {
      showError(error.response?.data?.message || 'Upload save failed');
    } finally {
      setSaving(false);
    }
  };

  const jumpToUploadResultRow = (bulkIndex) => {
    const meta = uploadRowMeta[bulkIndex];
    if (meta) setActiveSectionIdx(meta.sectionIndex);
  };

  if (!canManage) {
    return (
      <div className="max-w-3xl mx-auto bg-white rounded-xl border p-8 text-center">
        <h1 className="text-xl font-bold text-gray-900">Access denied</h1>
        <p className="text-sm text-gray-500 mt-2">Only admin / dean / registrar can set timetables.</p>
      </div>
    );
  }

  if (loading) {
    return <div className="text-center text-gray-500 py-16">Loading timetable setup…</div>;
  }

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <div className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-[#031f50] m-0">Set timetable</h1>
          <p className="mt-1 mb-0 text-sm text-gray-500">
            Live period set by admin:{' '}
            <strong className="text-[#031f50]">
              {settings?.settings?.academicYear?.yearLabel || `AY #${academicYearId}`}
            </strong>
            {' · '}Semester <strong className="text-[#031f50]">{semester}</strong>. Single, bulk, and
            Excel upload all save through the same engine.
          </p>
        </div>
        <div className="flex flex-wrap gap-3 text-sm items-center">
          <button
            type="button"
            onClick={() => {
              refreshPending();
              setShowPending((v) => !v);
            }}
            className={`inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 ${
              pendingItems.filter((p) => !p.resolved).length
                ? 'border-amber-300 bg-amber-50 text-amber-900'
                : 'border-gray-200 text-gray-600'
            }`}
          >
            <AlertTriangle size={14} />
            Missed / conflicts ({pendingItems.filter((p) => !p.resolved).length})
          </button>
          <Link to={appPath('timetables')} className="text-[#00628b] hover:underline">
            View saved timetables →
          </Link>
        </div>
      </div>

      {showPending && (
        <section className="bg-white rounded-xl border border-amber-200 p-4 shadow-sm space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="m-0 text-sm font-semibold text-amber-950">
              Tracked missed items (not saved)
            </h2>
            <div className="flex gap-2 text-xs">
              <button
                type="button"
                className="underline text-gray-600"
                onClick={() => {
                  pendingConflictsStore.clearResolved();
                  refreshPending();
                }}
              >
                Clear resolved
              </button>
              <button
                type="button"
                className="underline text-red-700"
                onClick={() => {
                  pendingConflictsStore.clearAll();
                  refreshPending();
                }}
              >
                Clear all
              </button>
            </div>
          </div>
          {pendingItems.length === 0 ? (
            <p className="m-0 text-sm text-gray-500">No tracked conflicts yet. Failed saves appear here.</p>
          ) : (
            <div className="max-h-72 overflow-y-auto space-y-2">
              {pendingItems.map((item) => (
                <div
                  key={item.id}
                  className={`rounded-lg border p-3 text-xs ${
                    item.resolved ? 'opacity-50 border-gray-100' : 'border-amber-100 bg-amber-50/50'
                  }`}
                >
                  <div className="flex flex-wrap justify-between gap-2">
                    <div>
                      <span className="font-semibold uppercase text-amber-900">{item.mode}</span>
                      <span className="text-gray-500 ml-2">
                        {new Date(item.createdAt).toLocaleString()}
                      </span>
                      <p className="m-0 mt-1 text-gray-800">{item.message}</p>
                      {item.attempt && (
                        <p className="m-0 mt-1 text-gray-600">
                          {[item.attempt.day, item.attempt.start && `${fmtConflictTime(item.attempt.start)}–${fmtConflictTime(item.attempt.end)}`]
                            .filter(Boolean)
                            .join(' · ')}
                          {item.attempt.moduleCode || item.attempt.moduleName
                            ? ` · ${[item.attempt.moduleCode, item.attempt.moduleName].filter(Boolean).join(' — ')}`
                            : ''}
                          {item.attempt.facilityName ? ` · ${item.attempt.facilityName}` : ''}
                        </p>
                      )}
                    </div>
                    <div className="flex gap-2 shrink-0">
                      {!item.resolved && (
                        <button
                          type="button"
                          className="text-[#00628b] underline"
                          onClick={() => {
                            pendingConflictsStore.markResolved(item.id);
                            refreshPending();
                          }}
                        >
                          Mark fixed
                        </button>
                      )}
                      <button
                        type="button"
                        className="text-red-600 underline"
                        onClick={() => {
                          pendingConflictsStore.remove(item.id);
                          refreshPending();
                        }}
                      >
                        Remove
                      </button>
                    </div>
                  </div>
                  {item.conflicts && <div className="mt-2"><ConflictBoxWithPlanViewer conflicts={item.conflicts} title="Conflict detail" EditModal={EditTeachingPlanModal} {...conflictViewerProps} /></div>}
                </div>
              ))}
            </div>
          )}
        </section>
      )}

      {/* Mode picker */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
        {MODES.map((m) => {
          const Icon = m.icon;
          const active = mode === m.id;
          return (
            <button
              key={m.id}
              type="button"
              onClick={() => {
                setMode(m.id);
                setConflicts(null);
                setUploadResult(null);
              }}
              className={`text-left rounded-xl border p-4 transition ${
                active
                  ? 'border-[#00628b] bg-[#e8f4f8] shadow-sm'
                  : 'border-gray-200 bg-white hover:border-gray-300'
              }`}
            >
              <div className="flex items-center gap-2">
                <Icon size={18} className={active ? 'text-[#00628b]' : 'text-gray-500'} />
                <span className="font-semibold text-gray-900">{m.label}</span>
              </div>
              <p className="m-0 mt-2 text-xs text-gray-500">{m.blurb}</p>
            </button>
          );
        })}
      </div>

      {/* Groups / promotion selector (shared by single + bulk) */}
      {(mode === 'single' || mode === 'bulk') && (
        <section className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-4">
          <h2 className="m-0 text-sm font-semibold text-[#031f50] flex items-center gap-2">
            <Users size={16} /> Promotion & groups
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <PickerButton
                label="Program"
                kind="program"
                placeholder="Search program…"
                valueLabel={
                  (() => {
                    const p = programs.find((x) => String(x.id) === String(selectedProgramId));
                    if (!p) return null;
                    const setup = programSetupById.get(String(p.id));
                    if (setup?.groups > 0) {
                      return `${p.name} · ${setup.groups} group(s)`;
                    }
                    if (setup?.intakes > 0) return `${p.name} · intake only`;
                    return `${p.name} · no groups yet`;
                  })()
                }
                onClick={() => setPicker({ type: 'program' })}
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600 mb-1">Intake / promotion</label>
              <select
                value={selectedIntakeId}
                onChange={(e) => {
                  const nextId = e.target.value;
                  setSelectedIntakeId(nextId);
                  const intake = intakes.find((i) => String(i.id) === String(nextId));
                  const gids = (intake?.groups || []).map((g) => Number(g.id));
                  // Auto-select all groups in this intake (user can unselect)
                  setSelectedGroupIds(gids);
                }}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                disabled={!selectedProgramId}
              >
                <option value="">Select intake…</option>
                {programIntakes.map((i) => (
                  <option key={i.id} value={i.id}>
                    Year {i.yearOfStudy} — {capitalizeCampusName(i.campus?.name) || `campus #${i.campusId}`} ({i.size || 0} students)
                  </option>
                ))}
              </select>
            </div>
            <div>
              <div className="flex items-center justify-between gap-2 mb-1">
                <label className="block text-xs font-medium text-gray-600 m-0">Groups (required)</label>
                {intakeGroups.length > 0 && (
                  <div className="flex gap-2 text-[10px]">
                    <button
                      type="button"
                      className="text-[#00628b] underline"
                      onClick={() => setSelectedGroupIds(intakeGroups.map((g) => Number(g.id)))}
                    >
                      Select all
                    </button>
                    <button
                      type="button"
                      className="text-gray-500 underline"
                      onClick={() => setSelectedGroupIds([])}
                    >
                      Clear
                    </button>
                  </div>
                )}
              </div>
              <div className="flex flex-wrap gap-2 min-h-[40px]">
                {intakeGroups.length === 0 ? (
                  <span className="text-xs text-gray-400 italic">Select an intake first</span>
                ) : (
                  intakeGroups.map((g) => {
                    const on = selectedGroupIds.map(Number).includes(Number(g.id));
                    return (
                      <button
                        key={g.id}
                        type="button"
                        onClick={() => toggleGroup(g.id)}
                        className={`px-3 py-1.5 rounded-lg text-xs border font-medium ${
                          on
                            ? 'bg-[#00628b] text-white border-[#00628b]'
                            : 'bg-white text-gray-700 border-amber-300'
                        }`}
                      >
                        {g.name} ({g.size || 0})
                        {on ? ' ✓' : ''}
                      </button>
                    );
                  })
                )}
              </div>
              {intakeGroups.length > 0 && selectedGroupIds.length === 0 && (
                <p className="m-0 mt-1.5 text-[11px] text-amber-700">
                  Select at least one group to enable Save.
                </p>
              )}
              {selectedGroupIds.length > 0 && (
                <p className="m-0 mt-1.5 text-[11px] text-emerald-700">
                  {selectedGroupIds.length} group(s) selected
                </p>
              )}
            </div>
          </div>
        </section>
      )}

      {conflicts && mode !== 'upload' && (
        <ConflictBoxWithPlanViewer
          conflicts={conflicts}
          title="Cannot save — facility or group conflict"
          EditModal={EditTeachingPlanModal}
          {...conflictViewerProps}
        />
      )}

      {/* SINGLE */}
      {mode === 'single' && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <section className="bg-white rounded-xl border p-5 space-y-4">
            <h2 className="m-0 text-sm font-semibold text-[#031f50] flex items-center gap-2">
              <Clock size={16} /> Sessions
            </h2>
            {sessions.map((s, idx) => (
              <div key={idx} className="grid grid-cols-3 gap-2">
                <select
                  value={s.day}
                  onChange={(e) =>
                    setSessions((prev) => prev.map((row, i) => (i === idx ? { ...row, day: e.target.value } : row)))
                  }
                  className="rounded-lg border border-gray-200 px-2 py-2 text-sm"
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
                    setSessions((prev) => prev.map((row, i) => (i === idx ? { ...row, start: e.target.value } : row)))
                  }
                  className="rounded-lg border border-gray-200 px-2 py-2 text-sm"
                />
                <div className="flex gap-1">
                  <input
                    type="time"
                    value={s.end}
                    onChange={(e) =>
                      setSessions((prev) => prev.map((row, i) => (i === idx ? { ...row, end: e.target.value } : row)))
                    }
                    className="flex-1 rounded-lg border border-gray-200 px-2 py-2 text-sm"
                  />
                  {sessions.length > 1 && (
                    <button type="button" className="p-2 text-red-600" onClick={() => setSessions((p) => p.filter((_, i) => i !== idx))}>
                      <Trash2 size={14} />
                    </button>
                  )}
                </div>
              </div>
            ))}
            <div className="flex flex-wrap gap-2">
              {timeBlocks.map((b) => (
                <button
                  key={b.id}
                  type="button"
                  className="text-xs px-2 py-1 rounded border border-gray-200 hover:bg-gray-50"
                  onClick={() =>
                    setSessions((prev) => [...prev, { day: prev[0]?.day || 'Monday', start: b.start, end: b.end }])
                  }
                >
                  + {b.label}
                </button>
              ))}
              <button
                type="button"
                className="text-xs px-2 py-1 rounded border border-gray-200"
                onClick={() => setSessions((prev) => [...prev, { day: 'Monday', start: '08:00', end: '13:00' }])}
              >
                <Plus size={12} className="inline" /> Custom
              </button>
            </div>
          </section>

          <section className="bg-white rounded-xl border p-5 space-y-4">
            <h2 className="m-0 text-sm font-semibold text-[#031f50] flex items-center gap-2">
              <BookOpen size={16} /> Module, lecturers & facility
            </h2>
            <PickerButton
              label="Module"
              kind="module"
              placeholder="Search module code or name…"
              valueLabel={
                (() => {
                  const m = programModules.find((x) => String(x.id) === String(moduleId));
                  return m ? `${m.code ? `${m.code} — ` : ''}${m.name}` : null;
                })()
              }
              onClick={() => setPicker({ type: 'module' })}
            />
            <PickerButton
              label="Leader lecturer"
              kind="lecturer"
              optional
              placeholder="Search lecturer…"
              valueLabel={
                (() => {
                  const u = lecturers.find((x) => String(x.id) === String(leaderId));
                  return u
                    ? `${lecturerPickerLabel(u)}${u.urEmail ? ` · ${u.urEmail}` : ''}`
                    : null;
                })()
              }
              onClick={() => setPicker({ type: 'leader' })}
            />
            <PickerButton
              label="Other lecturers"
              kind="lecturer"
              optional
              placeholder="Search & multi-select…"
              valueLabel={
                otherLecturerIds.length
                  ? lecturers
                      .filter((u) => otherLecturerIds.map(String).includes(String(u.id)))
                      .map((u) => lecturerPickerLabel(u))
                      .join(', ')
                  : null
              }
              onClick={() => setPicker({ type: 'others' })}
            />
            <div>
              <PickerButton
                label="Facility (free for selected sessions)"
                kind="facility"
                placeholder="Search free room, building, campus…"
                valueLabel={
                  (() => {
                    const f =
                      availableFacilities.find((x) => String(x.id) === String(facilityId)) ||
                      facilities.find((x) => String(x.id) === String(facilityId));
                    return f ? facilityCompactLabel(f) : null;
                  })()
                }
                onClick={() => setPicker({ type: 'facility' })}
              />
              <p className="m-0 mt-1 text-[11px] text-gray-400">
                {availableFacilities.length
                  ? `${availableFacilities.length} free facilities for these sessions`
                  : 'No free facilities for this day/time — all rooms are taken or below capacity'}
              </p>
            </div>
            <button
              type="button"
              disabled={saving || !selectedGroupIds.length || !moduleId || !facilityId}
              onClick={saveSingle}
              className="w-full rounded-lg bg-[#00628b] text-white py-2.5 text-sm font-semibold disabled:opacity-50"
            >
              {saving ? 'Saving…' : 'Save teaching plan'}
            </button>
            {!saving && (!selectedGroupIds.length || !moduleId || !facilityId) && (
              <p className="m-0 text-[11px] text-amber-700 text-center font-medium">
                Still need:{' '}
                {[
                  !selectedGroupIds.length ? 'select group(s)' : null,
                  !moduleId ? 'pick module' : null,
                  !facilityId ? 'pick facility' : null,
                ]
                  .filter(Boolean)
                  .join(' · ')}
              </p>
            )}
          </section>
        </div>
      )}

      {/* BULK */}
      {mode === 'bulk' && (
        <section className="bg-white rounded-xl border p-5 shadow-sm space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h2 className="m-0 text-sm font-semibold text-[#031f50]">Bulk rows (shared groups)</h2>
              <p className="m-0 mt-1 text-xs text-gray-500">
                Same fields as single entry per row: module, session, facility, leader &amp; other lecturers.
              </p>
            </div>
            <button
              type="button"
              className="inline-flex items-center gap-1 text-sm text-[#00628b] font-semibold"
              onClick={() => setBulkRows((p) => [...p, emptyBulkRow()])}
            >
              <Plus size={14} /> Add row
            </button>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full text-xs">
              <thead className="bg-gray-50 text-gray-600">
                <tr>
                  <th className="px-2 py-2 text-left">Module</th>
                  <th className="px-2 py-2 text-left">Day</th>
                  <th className="px-2 py-2 text-left">Start</th>
                  <th className="px-2 py-2 text-left">End</th>
                  <th className="px-2 py-2 text-left">Facility</th>
                  <th className="px-2 py-2 text-left">Leader</th>
                  <th className="px-2 py-2 text-left">Other lecturers</th>
                  <th className="px-2 py-2" />
                </tr>
              </thead>
              <tbody>
                {bulkRows.map((row, idx) => (
                  <tr key={idx} className="border-t align-top">
                    <td className="px-2 py-1.5 min-w-[180px]">
                      <button
                        type="button"
                        className="w-full text-left rounded-lg border border-gray-200 px-2 py-2 hover:border-[#00628b]/50"
                        onClick={() => setPicker({ type: 'bulk-module', rowIndex: idx })}
                      >
                        <span className="block text-[10px] uppercase text-gray-400">Module</span>
                        <span className="font-medium text-gray-900 line-clamp-2">
                          {(() => {
                            const m = programModules.find((x) => String(x.id) === String(row.moduleId));
                            return m ? `${m.code ? `${m.code} — ` : ''}${m.name}` : 'Search module…';
                          })()}
                        </span>
                      </button>
                    </td>
                    <td className="px-2 py-1.5">
                      <select
                        value={row.day}
                        onChange={(e) =>
                          setBulkRows((p) => p.map((r, i) => (i === idx ? { ...r, day: e.target.value } : r)))
                        }
                        className="rounded-lg border border-gray-200 px-2 py-2"
                      >
                        {days.map((d) => (
                          <option key={d} value={d}>
                            {d}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="px-2 py-1.5">
                      <input
                        type="time"
                        value={row.start}
                        onChange={(e) =>
                          setBulkRows((p) => p.map((r, i) => (i === idx ? { ...r, start: e.target.value } : r)))
                        }
                        className="rounded-lg border border-gray-200 px-2 py-2"
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <input
                        type="time"
                        value={row.end}
                        onChange={(e) =>
                          setBulkRows((p) => p.map((r, i) => (i === idx ? { ...r, end: e.target.value } : r)))
                        }
                        className="rounded-lg border border-gray-200 px-2 py-2"
                      />
                    </td>
                    <td className="px-2 py-1.5 min-w-[150px]">
                      <button
                        type="button"
                        className="w-full text-left rounded-lg border border-gray-200 px-2 py-2 hover:border-[#00628b]/50"
                        onClick={() => setPicker({ type: 'bulk-facility', rowIndex: idx })}
                      >
                        <span className="block text-[10px] uppercase text-gray-400">Facility</span>
                        <span className="font-medium text-gray-900 line-clamp-2">
                          {(() => {
                            const f = facilities.find((x) => String(x.id) === String(row.facilityId));
                            return f ? facilityCompactLabel(f) : 'Search facility…';
                          })()}
                        </span>
                      </button>
                    </td>
                    <td className="px-2 py-1.5 min-w-[150px]">
                      <button
                        type="button"
                        className="w-full text-left rounded-lg border border-gray-200 px-2 py-2 hover:border-[#00628b]/50"
                        onClick={() => setPicker({ type: 'bulk-leader', rowIndex: idx })}
                      >
                        <span className="block text-[10px] uppercase text-gray-400">Leader</span>
                        <span className="font-medium text-gray-900 line-clamp-2">
                          {(() => {
                            const u = lecturers.find((x) => String(x.id) === String(row.leaderLecturerId));
                            return u ? lecturerPickerLabel(u) : 'Search leader…';
                          })()}
                        </span>
                      </button>
                      {row.leaderLecturerId ? (
                        <button
                          type="button"
                          className="mt-1 text-[10px] text-gray-500 underline"
                          onClick={() =>
                            setBulkRows((p) =>
                              p.map((r, i) => (i === idx ? { ...r, leaderLecturerId: '' } : r))
                            )
                          }
                        >
                          Clear
                        </button>
                      ) : null}
                    </td>
                    <td className="px-2 py-1.5 min-w-[170px]">
                      <button
                        type="button"
                        className="w-full text-left rounded-lg border border-gray-200 px-2 py-2 hover:border-[#c45c26]/50"
                        onClick={() => setPicker({ type: 'bulk-others', rowIndex: idx })}
                      >
                        <span className="block text-[10px] uppercase text-gray-400">Other lecturers</span>
                        <span className="font-medium text-gray-900 line-clamp-2">
                          {(row.otherLecturerIds || []).length
                            ? lecturers
                                .filter((u) =>
                                  (row.otherLecturerIds || []).map(String).includes(String(u.id))
                                )
                                .map((u) => lecturerPickerLabel(u))
                                .join(', ')
                            : 'Search & multi-select…'}
                        </span>
                      </button>
                    </td>
                    <td className="px-2 py-1.5">
                      <button
                        type="button"
                        className="p-2 text-red-600"
                        onClick={() => setBulkRows((p) => p.filter((_, i) => i !== idx))}
                        disabled={bulkRows.length <= 1}
                        title="Remove row"
                      >
                        <Trash2 size={14} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {timeBlocks.length > 0 && (
            <div className="flex flex-wrap gap-2 items-center">
              <span className="text-[11px] text-gray-500">Quick time for last row:</span>
              {timeBlocks.map((b) => (
                <button
                  key={b.id}
                  type="button"
                  className="text-xs px-2 py-1 rounded border border-gray-200 hover:bg-gray-50"
                  onClick={() =>
                    setBulkRows((p) => {
                      if (!p.length) return [{ ...emptyBulkRow(), start: b.start, end: b.end }];
                      const next = [...p];
                      const last = { ...next[next.length - 1], start: b.start, end: b.end };
                      next[next.length - 1] = last;
                      return next;
                    })
                  }
                >
                  {b.label}
                </button>
              ))}
            </div>
          )}
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              disabled={saving || !selectedGroupIds.length}
              onClick={() => saveBulk({ dryRun: true })}
              className="px-4 py-2 rounded-lg border border-gray-200 text-sm"
            >
              Check conflicts
            </button>
            <button
              type="button"
              disabled={saving || !selectedGroupIds.length}
              onClick={() => saveBulk({ dryRun: false })}
              className="px-4 py-2 rounded-lg bg-[#00628b] text-white text-sm font-semibold disabled:opacity-50"
            >
              {saving ? 'Saving…' : 'Save all rows'}
            </button>
          </div>
        </section>
      )}

      {/* UPLOAD */}
      {mode === 'upload' && (
        <section className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden relative">
          <div className="h-1.5 w-full bg-gradient-to-r from-[#031f50] via-[#00628b] to-[#c45c26]" />

          <div className="p-5 sm:p-6 space-y-5">
            <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
              <div className="flex items-start gap-3 min-w-0">
                <span className="shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-[#e8f4f8] text-[#00628b] border border-[#00628b]/15">
                  <FileSpreadsheet size={22} />
                </span>
                <div className="min-w-0">
                  <h2 className="m-0 text-base font-semibold text-[#031f50]">Excel upload</h2>
                  <p className="m-0 mt-1 text-sm text-slate-500 max-w-2xl leading-relaxed">
                    Drop a <strong className="font-semibold text-slate-700">sample.xls</strong>-style
                    file. We parse Year / Program / Group banners, then match programs, modules,
                    rooms and intakes in the background.
                  </p>
                </div>
              </div>
              <div className="flex flex-wrap gap-2 shrink-0">
                <a
                  href={publicAssetUrl('templates/sample.xls')}
                  download
                  className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-[#00628b] hover:bg-[#e8f4f8] transition"
                >
                  <Download size={14} /> sample.xls
                </a>
                <a
                  href={publicAssetUrl('templates/timetable_import_template.csv')}
                  download
                  className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 transition"
                >
                  <Download size={14} /> CSV template
                </a>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="rounded-xl border border-slate-100 bg-[#f8fafc] p-4">
                <label className="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-2">
                  <MapPin size={12} /> Default campus
                  <span className="normal-case tracking-normal font-normal text-slate-400">
                    · for new intakes
                  </span>
                </label>
                <select
                  value={uploadCampusId}
                  onChange={(e) => setUploadCampusId(e.target.value)}
                  disabled={Boolean(uploadBusy)}
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25 disabled:opacity-60"
                >
                  <option value="">Select campus…</option>
                  {campuses.map((c) => (
                    <option key={c.id} value={c.id}>
                      {capitalizeCampusName(c.name)}
                    </option>
                  ))}
                </select>
              </div>
              <div className="rounded-xl border border-slate-100 bg-[#f8fafc] p-4">
                <label className="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-slate-400 font-semibold mb-2">
                  <Building2 size={12} /> Facility mode
                </label>
                <select
                  value={facilityMode}
                  onChange={(e) => setFacilityMode(e.target.value)}
                  disabled={Boolean(uploadBusy)}
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#00628b]/25 disabled:opacity-60"
                >
                  <option value="excel">Use Excel classrooms (match to system)</option>
                  <option value="auto">Auto free facilities (ignore Excel rooms)</option>
                  <option value="skip">Skip facilities (assign later)</option>
                </select>
                <p className="m-0 mt-2 text-[11px] text-slate-500 leading-snug">
                  {facilityMode === 'auto'
                    ? 'Ignores Excel rooms and picks free campus rooms. Each Excel section (G1&2, G3&4, …) stays separate — not merged into one plan.'
                    : facilityMode === 'skip'
                      ? 'Skips room matching. Save still works using a placeholder UNASSIGNED room — pick real facilities later from the preview or edit plans.'
                      : 'Keeps Excel structure: one plan per section/slot/group (many rows). Rooms matched from the Class room column.'}
                </p>
              </div>
            </div>

            {/* Dropzone */}
            <div
              role="button"
              tabIndex={0}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  if (!uploadBusy) fileInputRef.current?.click();
                }
              }}
              onClick={() => !uploadBusy && fileInputRef.current?.click()}
              onDragOver={(e) => {
                e.preventDefault();
                e.stopPropagation();
              }}
              onDrop={(e) => {
                e.preventDefault();
                e.stopPropagation();
                if (uploadBusy) return;
                const f = e.dataTransfer.files?.[0];
                if (f) handleExcel(f);
              }}
              className={`relative rounded-2xl border-2 border-dashed px-4 py-8 sm:py-10 text-center transition ${
                uploadBusy
                  ? 'border-[#00628b]/40 bg-[#e8f4f8]/60 cursor-wait'
                  : 'border-slate-300 bg-[#fbfcfe] hover:border-[#00628b] hover:bg-[#e8f4f8]/40 cursor-pointer'
              }`}
            >
              <input
                ref={fileInputRef}
                type="file"
                accept=".xlsx,.xls,.csv"
                className="hidden"
                disabled={Boolean(uploadBusy)}
                onChange={(e) => {
                  const f = e.target.files?.[0];
                  if (f) handleExcel(f);
                }}
              />
              {uploadBusy ? (
                <div className="space-y-3">
                  <div className="mx-auto w-12 h-12 rounded-2xl bg-white border border-[#00628b]/20 flex items-center justify-center text-[#00628b] shadow-sm">
                    <Loader2 size={24} className="animate-spin" />
                  </div>
                  <div>
                    <p className="m-0 text-sm font-semibold text-[#031f50]">
                      Processing {uploadBusy.fileName}
                    </p>
                    <p className="m-0 mt-1 text-xs text-slate-600">{uploadBusy.detail}</p>
                  </div>
                  <div className="max-w-md mx-auto flex flex-wrap justify-center gap-1.5 pt-1">
                    {[
                      { id: 'read', label: 'Read' },
                      { id: 'parse', label: 'Parse' },
                      { id: 'match', label: 'Match' },
                      { id: 'groups', label: 'Groups' },
                      { id: 'rematch', label: 'Rematch' },
                      { id: 'facilities', label: 'Facilities' },
                    ].map((s, i, arr) => {
                      const order = arr.map((x) => x.id);
                      const cur = order.indexOf(uploadBusy.step);
                      const mine = order.indexOf(s.id);
                      const done = mine >= 0 && mine < cur;
                      const active = s.id === uploadBusy.step;
                      // skip steps that may not run
                      if (
                        (s.id === 'groups' || s.id === 'rematch') &&
                        cur < order.indexOf('groups') &&
                        !['groups', 'rematch', 'facilities'].includes(uploadBusy.step)
                      ) {
                        return null;
                      }
                      if (
                        s.id === 'facilities' &&
                        facilityMode !== 'auto' &&
                        uploadBusy.step !== 'facilities'
                      ) {
                        return null;
                      }
                      return (
                        <span
                          key={s.id}
                          className={`inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-semibold border ${
                            active
                              ? 'bg-[#00628b] text-white border-[#00628b]'
                              : done
                                ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                : 'bg-white text-slate-400 border-slate-200'
                          }`}
                        >
                          {done ? '✓ ' : active ? '… ' : ''}
                          {s.label}
                        </span>
                      );
                    })}
                  </div>
                </div>
              ) : (
                <div className="space-y-2">
                  <div className="mx-auto w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-[#c45c26] shadow-sm">
                    <Upload size={22} />
                  </div>
                  <p className="m-0 text-sm font-semibold text-[#031f50]">
                    Drop Excel / CSV here, or click to browse
                  </p>
                  <p className="m-0 text-xs text-slate-500">
                    Accepts .xls, .xlsx, .csv
                    {uploadFileName ? (
                      <>
                        {' '}
                        · Last file: <span className="font-medium text-slate-700">{uploadFileName}</span>
                      </>
                    ) : null}
                  </p>
                </div>
              )}
            </div>

            {parseMeta && !uploadBusy && (
              <div className="flex flex-wrap gap-2 text-[11px]">
                <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                  {parseMeta.sheets} sheet(s)
                </span>
                <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-medium">
                  {parseMeta.titlesSeen} section title(s)
                </span>
                <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 font-medium border border-emerald-100">
                  {parseMeta.sectionsWithRows ?? parsedSections.length} with rows
                </span>
                {matchedSections.length > 0 && (
                  <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-[#e8f4f8] text-[#00628b] font-semibold border border-[#00628b]/15">
                    {matchedSections.length} matched section(s)
                  </span>
                )}
              </div>
            )}
          </div>

          {/* Dim results while loading in background */}
          {uploadBusy && matchedSections.length > 0 && (
            <div className="absolute inset-x-0 bottom-0 top-1/2 bg-white/50 backdrop-blur-[1px] pointer-events-none" />
          )}

          {matchedSections.length > 0 && (
            <div className={`px-5 sm:px-6 pb-5 sm:pb-6 space-y-4 border-t border-slate-100 ${uploadBusy ? 'opacity-50 pointer-events-none' : ''}`}>
              {matchedSections.some((s) => s.needsGroups) && (
                <div className="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div>
                    <strong>Missing intakes / groups.</strong> Create all promotions from these Excel
                    sections (Year + Groups + student size), then rematch automatically.
                    {!uploadCampusId && (
                      <span className="block text-red-700 mt-1">Select a default campus first.</span>
                    )}
                  </div>
                  <button
                    type="button"
                    disabled={saving || !uploadCampusId || Boolean(uploadBusy)}
                    onClick={createMissingGroups}
                    className="shrink-0 px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-semibold disabled:opacity-50"
                  >
                    Save all intakes &amp; groups now
                  </button>
                </div>
              )}

              <div className="flex flex-wrap gap-2">
                <button
                  type="button"
                  disabled={saving || !matchedSections.some((s) => s.needsGroups) || Boolean(uploadBusy)}
                  onClick={createMissingGroups}
                  className="px-4 py-2 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 text-sm font-medium disabled:opacity-50"
                >
                  Save all intakes &amp; groups now
                </button>
                <button
                  type="button"
                  disabled={saving || Boolean(uploadBusy)}
                  onClick={() => rematchSections()}
                  className="px-4 py-2 rounded-lg border text-sm"
                >
                  Rematch
                </button>
                <button
                  type="button"
                  disabled={saving || Boolean(uploadBusy)}
                  onClick={runAutoFacilities}
                  className="px-4 py-2 rounded-lg border border-[#00628b] text-[#00628b] text-sm font-medium"
                >
                  Auto-assign free facilities
                </button>
                <button
                  type="button"
                  disabled={saving || Boolean(uploadBusy)}
                  onClick={() => saveUpload({ dryRun: true })}
                  className="px-4 py-2 rounded-lg border text-sm"
                >
                  Check conflicts
                </button>
                <button
                  type="button"
                  disabled={saving || Boolean(uploadBusy)}
                  onClick={() => saveUpload({ dryRun: false })}
                  className="px-4 py-2 rounded-lg bg-[#00628b] text-white text-sm font-semibold disabled:opacity-50"
                >
                  {saving ? 'Working…' : 'Save all matched rows'}
                </button>
                <button
                  type="button"
                  disabled={saving || Boolean(uploadBusy) || activeSectionIdx == null}
                  onClick={() => saveUpload({ dryRun: true, sectionIndex: activeSectionIdx })}
                  className="px-4 py-2 rounded-lg border text-sm"
                >
                  Check this section
                </button>
                <button
                  type="button"
                  disabled={saving || Boolean(uploadBusy) || activeSectionIdx == null}
                  onClick={() => saveUpload({ dryRun: false, sectionIndex: activeSectionIdx })}
                  className="px-4 py-2 rounded-lg border border-[#00628b] text-[#00628b] text-sm font-semibold disabled:opacity-50"
                >
                  Save this section
                </button>
                <button
                  type="button"
                  disabled={Boolean(uploadBusy) || activeSectionIdx == null || !matchedSections[activeSectionIdx]}
                  onClick={() => {
                    try {
                      exportUploadSectionsPdf([matchedSections[activeSectionIdx]], {
                        yearLabel: settings?.settings?.academicYear?.yearLabel,
                        academicYearId,
                        semester,
                        fileName: `timetable-preview-section-${activeSectionIdx + 1}`,
                      });
                      showSuccess('PDF downloaded');
                    } catch (e) {
                      showError(e.message || 'Failed to download PDF preview');
                    }
                  }}
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-amber-300 bg-amber-50 text-amber-950 text-sm font-medium disabled:opacity-50"
                >
                  <Download size={14} /> PDF this section
                </button>
                <button
                  type="button"
                  disabled={Boolean(uploadBusy) || !matchedSections.length}
                  onClick={() => {
                    try {
                      exportUploadSectionsPdf(matchedSections, {
                        yearLabel: settings?.settings?.academicYear?.yearLabel,
                        academicYearId,
                        semester,
                        fileName: 'timetable-preview-all-sections',
                      });
                      showSuccess('PDF downloaded');
                    } catch (e) {
                      showError(e.message || 'Failed to download PDF preview');
                    }
                  }}
                  className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border text-sm font-medium disabled:opacity-50"
                >
                  <Download size={14} /> PDF all sections
                </button>
              </div>

              {uploadResult && mode === 'upload' && (
                <UploadResultPanel
                  uploadResult={uploadResult}
                  onJumpRow={jumpToUploadResultRow}
                  conflictViewerProps={conflictViewerProps}
                />
              )}

              <div className="w-full space-y-4">
                {/* Sections on top — full width */}
                <div className="w-full">
                  <p className="m-0 mb-2 text-[10px] uppercase tracking-wider text-slate-400 font-semibold">
                    Excel sections ({matchedSections.length})
                  </p>
                  <div className="w-full grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2">
                    {matchedSections.map((sec, idx) => (
                      <button
                        key={idx}
                        type="button"
                        onClick={() => setActiveSectionIdx(idx)}
                        className={`w-full text-left rounded-xl border p-3 text-xs transition ${
                          activeSectionIdx === idx
                            ? 'border-[#00628b] bg-[#e8f4f8] shadow-sm ring-1 ring-[#00628b]/20'
                            : 'border-slate-200 bg-white hover:border-[#00628b]/40'
                        }`}
                      >
                        <div className="font-semibold text-[#031f50] leading-snug">{sec.title}</div>
                        <div className="mt-1.5 text-slate-500">
                          Y{sec.year} · Groups {(sec.groupNumbers || []).join('&') || '—'} ·{' '}
                          {sec.stats?.rows || 0} rows
                        </div>
                        <div className="mt-1.5 flex flex-wrap gap-1">
                          {sec.program ? (
                            <span className="inline-flex items-center gap-1 text-emerald-700 font-medium">
                              <CheckCircle2 size={12} /> {sec.program.name}
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 text-amber-700 font-medium">
                              <AlertTriangle size={12} /> Program not matched
                            </span>
                          )}
                          {sec.needsGroups && (
                            <span className="text-amber-700">
                              · needs groups
                              {(sec.missingGroupNumbers || []).length
                                ? ` (${sec.missingGroupNumbers.join(', ')})`
                                : ''}
                            </span>
                          )}
                        </div>
                      </button>
                    ))}
                  </div>
                </div>

                {/* Active section detail + full-width table */}
                <div className="w-full space-y-3">
                  {(() => {
                    const sec = matchedSections[activeSectionIdx];
                    if (!sec) return null;
                    return (
                      <>
                        <div className="w-full rounded-xl border border-slate-200 bg-[#f8fafc] p-4 space-y-3">
                          <div className="flex flex-wrap items-start justify-between gap-2">
                            <p className="m-0 text-sm font-semibold text-[#031f50]">{sec.title}</p>
                            <div className="flex flex-wrap gap-2">
                              <button
                                type="button"
                                disabled={saving || Boolean(uploadBusy)}
                                onClick={() => saveUpload({ dryRun: true, sectionIndex: activeSectionIdx })}
                                className="px-3 py-1.5 rounded-lg border bg-white text-xs font-medium disabled:opacity-50"
                              >
                                Check this section
                              </button>
                              <button
                                type="button"
                                disabled={saving || Boolean(uploadBusy)}
                                onClick={() => saveUpload({ dryRun: false, sectionIndex: activeSectionIdx })}
                                className="px-3 py-1.5 rounded-lg bg-[#00628b] text-white text-xs font-semibold disabled:opacity-50"
                              >
                                Save this section
                              </button>
                              <button
                                type="button"
                                disabled={Boolean(uploadBusy)}
                                onClick={() => {
                                  try {
                                    exportUploadSectionsPdf([sec], {
                                      yearLabel: settings?.settings?.academicYear?.yearLabel,
                                      academicYearId,
                                      semester,
                                      fileName: `timetable-preview-${(sec.title || 'section')
                                        .replace(/[^\w\-]+/g, '_')
                                        .slice(0, 60)}`,
                                    });
                                    showSuccess('PDF downloaded');
                                  } catch (e) {
                                    showError(e.message || 'Failed to download PDF preview');
                                  }
                                }}
                                className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-amber-300 bg-amber-50 text-amber-950 text-xs font-medium disabled:opacity-50"
                              >
                                <Download size={12} /> Download PDF preview
                              </button>
                            </div>
                          </div>
                          {(sec.title || parsedSections[activeSectionIdx]?.program_hint) && (
                            <p className="m-0 text-[11px] text-slate-500">
                              From Excel:{' '}
                              <span className="font-medium text-slate-700">
                                {parsedSections[activeSectionIdx]?.program_hint ||
                                  sec.title ||
                                  '—'}
                              </span>
                              <span className="text-slate-400">
                                {' '}
                                — if the match below is wrong, search and pick the exact program.
                              </span>
                            </p>
                          )}
                          <div className="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-3 items-start">
                            <div>
                              <PickerButton
                                label={sec.program?.id ? 'Matched program (click to change)' : 'Program not matched — pick exact'}
                                kind="program"
                                placeholder="Search all programs…"
                                valueLabel={
                                  sec.program
                                    ? `${sec.program.name}${sec.program.code ? ` · ${sec.program.code}` : ''}`
                                    : null
                                }
                                className={
                                  sec.program?.id
                                    ? ''
                                    : '!border-amber-300 !bg-amber-50/50'
                                }
                                onClick={() =>
                                  setPicker({
                                    type: 'upload-program',
                                    sectionIndex: activeSectionIdx,
                                  })
                                }
                              />
                              <div className="mt-2 flex flex-wrap gap-2">
                                <button
                                  type="button"
                                  disabled={!sec.program?.id || saving || Boolean(uploadBusy)}
                                  onClick={() => {
                                    if (!sec.program?.id) return;
                                    setForcedProgram(activeSectionIdx, sec.program.id, { allSections: true });
                                    showSuccess('Applied this program to all Excel sections — rematching…');
                                  }}
                                  className="text-[11px] font-semibold text-[#00628b] underline disabled:opacity-40 disabled:no-underline"
                                >
                                  Apply this program to all sections
                                </button>
                                {sec.program?.id && (
                                  <button
                                    type="button"
                                    disabled={saving || Boolean(uploadBusy)}
                                    onClick={() => setForcedProgram(activeSectionIdx, '')}
                                    className="text-[11px] text-slate-500 underline disabled:opacity-40"
                                  >
                                    Clear match
                                  </button>
                                )}
                              </div>
                            </div>
                            <div className="text-slate-600 space-y-1 text-xs lg:pt-1 lg:min-w-[14rem]">
                              <div>
                                Size hint: {sec.sizeHint?.size || '—'} ({sec.sizeHint?.mode || '—'})
                                {(sec.groupNumbers || []).length
                                  ? ` → Groups ${(sec.groupNumbers || []).join(' & ')}`
                                  : ''}
                              </div>
                              <div>
                                System groups:{' '}
                                {(sec.groups || []).length
                                  ? (sec.groups || []).map((g) => `${g.name}(${g.size})`).join(', ')
                                  : 'none — click “Save all intakes & groups now”'}
                              </div>
                              {(sec.missingGroupNumbers || []).length > 0 && (
                                <div className="text-amber-700">
                                  Missing: Group {(sec.missingGroupNumbers || []).join(', ')}
                                </div>
                              )}
                            </div>
                          </div>
                        </div>

                        <div className="w-full overflow-x-auto border border-slate-200 rounded-xl bg-white">
                          <table className="w-full min-w-[960px] text-xs table-fixed">
                            <colgroup>
                              <col className="w-[7%]" />
                              <col className="w-[12%]" />
                              <col className="w-[22%]" />
                              <col className="w-[20%]" />
                              <col className="w-[24%]" />
                              <col className="w-[15%]" />
                            </colgroup>
                            <thead className="bg-[#031f50] text-white">
                              <tr>
                                <th className="px-3 py-2.5 text-left font-semibold">Status</th>
                                <th className="px-3 py-2.5 text-left font-semibold">Day / Time</th>
                                <th className="px-3 py-2.5 text-left font-semibold">Module</th>
                                <th className="px-3 py-2.5 text-left font-semibold">Facility</th>
                                <th className="px-3 py-2.5 text-left font-semibold">Lecturers</th>
                                <th className="px-3 py-2.5 text-left font-semibold">Groups</th>
                              </tr>
                            </thead>
                            <tbody>
                              {(sec.rows || []).map((r) => {
                                const hasConflict =
                                  r.saveConflict &&
                                  ((r.saveConflict.facility || []).length > 0 ||
                                    Object.keys(r.saveConflict.groups || {}).length > 0);
                                const isMerged = r.status === 'skipped' || r.mergedAway;
                                const isCombined = Boolean(r.combinedClass) && (r.groups || []).length > 1;
                                return (
                                <tr
                                  key={r.rowIndex}
                                  className={`border-t align-top ${
                                    hasConflict
                                      ? 'bg-red-50'
                                      : isMerged
                                        ? 'bg-slate-50 opacity-70'
                                        : isCombined
                                          ? 'bg-emerald-50/40'
                                          : ''
                                  }`}
                                >
                                  <td className="px-2 py-1">
                                    {isMerged ? (
                                      <span className="text-[10px] font-semibold text-slate-500 uppercase">Merged</span>
                                    ) : hasConflict ? (
                                      <span className="inline-flex items-center gap-1 text-red-700 font-semibold">
                                        <AlertTriangle size={14} /> conflict
                                      </span>
                                    ) : isCombined ? (
                                      <span className="inline-flex items-center gap-1 text-emerald-800 font-semibold text-[10px]">
                                        <CheckCircle2 size={14} /> Combined
                                      </span>
                                    ) : r.status === 'ok' ? (
                                      <CheckCircle2 size={14} className="text-green-600" />
                                    ) : (
                                      <AlertTriangle
                                        size={14}
                                        className={r.status === 'error' ? 'text-red-500' : 'text-amber-500'}
                                      />
                                    )}
                                  </td>
                                  <td className="px-2 py-1 whitespace-nowrap">
                                    {r.day} {fmtConflictTime(r.start)}-{fmtConflictTime(r.end)}
                                    {r.students ? (
                                      <div className="text-gray-400">{r.students} students</div>
                                    ) : null}
                                  </td>
                                  <td className="px-2 py-1 min-w-[180px]">
                                    <button
                                      type="button"
                                      className={`w-full text-left rounded-lg border px-2 py-1.5 text-xs hover:border-[#00628b]/50 ${
                                        r.module?.id ? 'border-gray-200' : 'border-amber-300 bg-amber-50/40'
                                      }`}
                                      onClick={() =>
                                        setPicker({
                                          type: 'upload-module',
                                          sectionIndex: activeSectionIdx,
                                          rowIndex: r.rowIndex,
                                        })
                                      }
                                    >
                                      <span className="block text-[10px] uppercase text-gray-400">Module</span>
                                      <span className="font-medium text-gray-900 line-clamp-2">
                                        {r.module
                                          ? `${(r.excel?.moduleCode || r.module.code) ? `${r.excel?.moduleCode || r.module.code} — ` : ''}${
                                              r.excel?.moduleName &&
                                              r.excel.moduleName !== (r.excel?.moduleCode || r.module.code)
                                                ? r.excel.moduleName
                                                : r.module.name || r.excel?.moduleName || '—'
                                            }`
                                          : 'Search & pick module…'}
                                      </span>
                                    </button>
                                    {r.excel?.moduleName &&
                                    r.excel.moduleName !== (r.excel?.moduleCode || r.module?.code) ? (
                                      <div className="text-emerald-700 mt-1 text-[10px]">
                                        Excel name saved
                                        {r.excel?.moduleCode ? ` · code ${r.excel.moduleCode}` : ''}
                                      </div>
                                    ) : r.module?.fromExcel ? (
                                      <div className="text-amber-700 mt-1 text-[10px]">
                                        Excel code saved — course name missing from sheet column
                                      </div>
                                    ) : (r.excel?.moduleCode || r.excel?.moduleName) ? (
                                      <div className="text-gray-400 mt-1 text-[10px]">
                                        Excel: {[r.excel.moduleCode, r.excel.moduleName].filter(Boolean).join(' — ')}
                                      </div>
                                    ) : null}
                                  </td>
                                  <td className="px-2 py-1 min-w-[160px]">
                                    <button
                                      type="button"
                                      className={`w-full text-left rounded-lg border px-2 py-1.5 text-xs hover:border-[#00628b]/50 ${
                                        hasConflict || !r.facility?.id
                                          ? 'border-red-400 bg-red-50/40'
                                          : 'border-gray-200'
                                      }`}
                                      onClick={() => openUploadFacilityPicker(activeSectionIdx, r.rowIndex)}
                                    >
                                      <span className="block text-[10px] uppercase text-gray-400">Facility</span>
                                      <span className="font-medium text-gray-900 line-clamp-2">
                                        {r.facility
                                          ? facilityCompactLabel(r.facility)
                                          : 'Search & pick facility…'}
                                      </span>
                                    </button>
                                    {r.excel?.classroom ? (
                                      <div className="text-gray-400 mt-1 text-[10px]">Excel: {r.excel.classroom}</div>
                                    ) : null}
                                    {hasConflict && (r.saveConflict.facility || []).length > 0 && (
                                      <div className="text-red-700 mt-1 font-medium">Room clash — change facility</div>
                                    )}
                                  </td>
                                  <td className="px-2 py-1 min-w-[200px]">
                                    <button
                                      type="button"
                                      className={`w-full text-left rounded border px-2 py-1.5 text-xs hover:border-[#00628b]/50 ${
                                        r.lecturers?.leader?.id ? 'border-gray-200' : 'border-amber-300 bg-amber-50/40'
                                      }`}
                                      onClick={() =>
                                        setPicker({
                                          type: 'upload-leader',
                                          sectionIndex: activeSectionIdx,
                                          rowIndex: r.rowIndex,
                                        })
                                      }
                                    >
                                      <span className="block text-[10px] uppercase text-gray-400">Leader</span>
                                      <span className="font-medium text-gray-900">
                                        {r.lecturers?.leader?.names
                                          ? lecturerPickerLabel(r.lecturers.leader)
                                          : 'Search & pick leader…'}
                                      </span>
                                    </button>
                                    {(r.lecturers?.others || []).length > 0 && (
                                      <div className="mt-1 text-[10px] text-gray-600">
                                        +
                                        {(r.lecturers.others || [])
                                          .map((o) => lecturerPickerLabel(o))
                                          .filter(Boolean)
                                          .join(', ')}
                                      </div>
                                    )}
                                    <button
                                      type="button"
                                      className="mt-1 text-[10px] text-[#00628b] underline"
                                      onClick={() =>
                                        setPicker({
                                          type: 'upload-others',
                                          sectionIndex: activeSectionIdx,
                                          rowIndex: r.rowIndex,
                                        })
                                      }
                                    >
                                      Edit other lecturers
                                    </button>
                                    {r.excel?.lecturers ? (
                                      <div className="text-gray-400 mt-1 text-[10px]">Excel: {r.excel.lecturers}</div>
                                    ) : null}
                                    {(r.missedLecturers || []).length > 0 && (
                                      <div className="mt-1.5 space-y-1">
                                        <div className="text-[10px] font-semibold text-amber-800">
                                          Not in system yet — search &amp; assign:
                                        </div>
                                        {(r.missedLecturers || []).map((name) => (
                                          <button
                                            key={name}
                                            type="button"
                                            className="block w-full text-left rounded border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] text-amber-950 hover:border-[#00628b]"
                                            onClick={() =>
                                              setPicker({
                                                type: 'upload-resolve-missed',
                                                sectionIndex: activeSectionIdx,
                                                rowIndex: r.rowIndex,
                                                excelName: name,
                                              })
                                            }
                                          >
                                            “{name}” → search system…
                                          </button>
                                        ))}
                                      </div>
                                    )}
                                  </td>
                                  <td className="px-2 py-1">
                                    {(r.groups || []).map((g) => g.name).join(', ') || '—'}
                                    {isCombined && (
                                      <div className="text-emerald-800 mt-1 font-semibold text-[10px]">
                                        Combined class · {(r.groups || []).length} groups · one room
                                      </div>
                                    )}
                                    {isMerged && (
                                      <div className="text-slate-500 mt-1 text-[10px]">
                                        {(r.warnings && r.warnings[0]) || 'Merged into another row'}
                                      </div>
                                    )}
                                    {hasConflict && Object.keys(r.saveConflict.groups || {}).length > 0 && (
                                      <div className="text-violet-800 mt-1 font-semibold text-[10px] uppercase tracking-wide">
                                        Group conflict
                                      </div>
                                    )}
                                    {hasConflict && (r.saveConflict.facility || []).length > 0 && (
                                      <div className="text-orange-800 mt-1 font-semibold text-[10px] uppercase tracking-wide">
                                        Room conflict
                                      </div>
                                    )}
                                    {(quietRowWarnings(r.warnings) || []).length > 0 && !hasConflict && !isMerged && (
                                      <div className="text-amber-700 mt-1">{quietRowWarnings(r.warnings)[0]}</div>
                                    )}
                                    {hasConflict && (
                                      <div className="mt-2">
                                        <ConflictBoxWithPlanViewer
                                          conflicts={r.saveConflict}
                                          conflictKinds={getConflictKindsFromPayload(r.saveConflict)}
                                          title="Conflict details"
                                          EditModal={EditTeachingPlanModal}
                                          {...conflictViewerProps}
                                        />
                                      </div>
                                    )}
                                  </td>
                                </tr>
                                );
                              })}
                            </tbody>
                          </table>
                        </div>
                      </>
                    );
                  })()}
                </div>
              </div>
            </div>
          )}
        </section>
      )}

      {uploadResult && mode !== 'upload' && (
        <UploadResultPanel
          uploadResult={uploadResult}
          onJumpRow={jumpToUploadResultRow}
          conflictViewerProps={conflictViewerProps}
        />
      )}

      {/* Searchable pickers */}
      <SearchablePicker
        open={picker?.type === 'program'}
        onClose={() => setPicker(null)}
        kind="program"
        title="Choose program"
        subtitle="Programs with intakes & groups listed first"
        placeholder="Search program name or code…"
        items={programsForPicker}
        value={selectedProgramId}
        getLabel={(p) => p.name}
        getMeta={(p) => {
          const setup = programSetupById.get(String(p.id));
          const base = [p.code, p.school?.name].filter(Boolean).join(' · ');
          if (!setup) return base;
          const years = [...new Set(setup.years)].sort((a, b) => a - b);
          const detail = `${setup.intakes} intake(s) · ${setup.groups} group(s)${
            years.length ? ` · Y${years.join(',')}` : ''
          }`;
          return [base, detail].filter(Boolean).join(' · ');
        }}
        getBadge={(p) => {
          const setup = programSetupById.get(String(p.id));
          if (setup?.groups > 0) {
            return { label: `${setup.groups} group(s) ready`, tone: 'ok' };
          }
          if (setup?.intakes > 0) {
            return { label: 'Intake only — no groups yet', tone: 'muted' };
          }
          return { label: 'No intake / groups yet', tone: 'muted' };
        }}
        onSelect={(p) => {
          setSelectedProgramId(String(p.id));
          setSelectedIntakeId('');
          setSelectedGroupIds([]);
        }}
      />
      <SearchablePicker
        open={picker?.type === 'upload-program'}
        onClose={() => setPicker(null)}
        kind="program"
        title="Pick exact program"
        subtitle="Excel names are often messy — search and choose the correct system program"
        placeholder="Search program name or code…"
        hint="All programs listed — candidates first when available"
        items={(() => {
          const si = picker?.sectionIndex;
          const sec = si != null ? matchedSections[si] : null;
          const candidateIds = new Set((sec?.programCandidates || []).map((c) => String(c.id)));
          // Prefer candidates at top, then rest of catalog
          const rest = programsForPicker.filter((p) => !candidateIds.has(String(p.id)));
          const tops = (sec?.programCandidates || [])
            .map((c) => programs.find((p) => String(p.id) === String(c.id)) || c)
            .filter(Boolean);
          return tops.length ? [...tops, ...rest] : programsForPicker;
        })()}
        value={(() => {
          const si = picker?.sectionIndex;
          if (si == null) return null;
          return matchedSections[si]?.program?.id || null;
        })()}
        getLabel={(p) => p.name}
        getMeta={(p) => {
          const setup = programSetupById.get(String(p.id));
          const base = [p.code, p.school?.name].filter(Boolean).join(' · ');
          if (!setup) return base;
          const detail = `${setup.intakes} intake(s) · ${setup.groups} group(s)`;
          return [base, detail].filter(Boolean).join(' · ');
        }}
        getBadge={(p) => {
          const si = picker?.sectionIndex;
          const sec = si != null ? matchedSections[si] : null;
          const cand = (sec?.programCandidates || []).find((c) => String(c.id) === String(p.id));
          if (cand?.score != null) {
            return { label: `Excel match score ${cand.score}`, tone: 'ok' };
          }
          const setup = programSetupById.get(String(p.id));
          if (setup?.groups > 0) return { label: `${setup.groups} group(s) ready`, tone: 'ok' };
          if (setup?.intakes > 0) return { label: 'Intake only', tone: 'muted' };
          return { label: 'No intake / groups yet', tone: 'muted' };
        }}
        onSelect={(p) => {
          const si = picker?.sectionIndex;
          if (si == null) return;
          setForcedProgram(si, p.id);
          showSuccess(`Program set to “${p.name}” — rematching section…`);
        }}
      />
      <SearchablePicker
        open={picker?.type === 'module'}
        onClose={() => setPicker(null)}
        kind="module"
        title="Choose module"
        subtitle="Search by module code or name"
        placeholder="e.g. AF80133 or Business Mathematics"
        items={programModules}
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
        subtitle="Optional — module leader for this plan"
        placeholder="Search name or email…"
        items={[{ id: '', names: '— None —' }, ...lecturers]}
        value={leaderId || ''}
        getLabel={lecturerPickerLabel}
        getMeta={lecturerPickerMeta}
        onSelect={(u) => {
          setLeaderId(u.id ? String(u.id) : '');
          if (u.id) {
            setOtherLecturerIds((prev) => prev.filter((id) => Number(id) !== Number(u.id)));
          }
        }}
      />
      <SearchablePicker
        open={picker?.type === 'others'}
        onClose={() => setPicker(null)}
        kind="lecturer"
        accent="amber"
        title="Other lecturers"
        subtitle="Multi-select · search, chip-remove, or filter to selected only"
        placeholder="Search lecturer name or email…"
        items={lecturers.filter((u) => String(u.id) !== String(leaderId))}
        value={otherLecturerIds}
        multiple
        getLabel={lecturerPickerLabel}
        getMeta={lecturerPickerMeta}
        onSelect={(list) => setOtherLecturerIds(list.map((u) => u.id))}
        confirmLabel="Apply lecturers"
      />
      <SearchablePicker
        open={picker?.type === 'facility'}
        onClose={() => setPicker(null)}
        kind="facility"
        title="Choose free facility"
        subtitle="Only rooms free for the selected day/time (not already booked)"
        placeholder="Search room, building, site, campus…"
        emptyText="No free rooms for this day/time — all matching facilities are taken or below capacity"
        items={availableFacilities}
        value={facilityId}
        getLabel={facilityPickerLabel}
        getMeta={facilityPickerMeta}
        filterItem={(f, q) => facilitySearchHaystack(f).includes(q)}
        onSelect={(f) => setFacilityId(String(f.id))}
      />
      <SearchablePicker
        open={picker?.type === 'bulk-module'}
        onClose={() => setPicker(null)}
        kind="module"
        title="Choose module"
        placeholder="Search module code or name…"
        items={programModules}
        value={picker?.rowIndex != null ? bulkRows[picker.rowIndex]?.moduleId : null}
        getLabel={(m) => `${m.code ? `${m.code} — ` : ''}${m.name}`}
        getMeta={(m) => `Year ${m.year ?? '—'} · Sem ${m.semester ?? '—'}`}
        onSelect={(m) => {
          const idx = picker?.rowIndex;
          if (idx == null) return;
          setBulkRows((p) => p.map((r, i) => (i === idx ? { ...r, moduleId: String(m.id) } : r)));
        }}
      />
      <SearchablePicker
        open={picker?.type === 'bulk-facility'}
        onClose={() => setPicker(null)}
        kind="facility"
        title="Choose facility"
        subtitle="Room, building, campus, type & capacity"
        placeholder="Search room, building, site, campus…"
        items={facilities}
        value={picker?.rowIndex != null ? bulkRows[picker.rowIndex]?.facilityId : null}
        getLabel={facilityPickerLabel}
        getMeta={facilityPickerMeta}
        filterItem={(f, q) => facilitySearchHaystack(f).includes(q)}
        onSelect={(f) => {
          const idx = picker?.rowIndex;
          if (idx == null) return;
          setBulkRows((p) => p.map((r, i) => (i === idx ? { ...r, facilityId: String(f.id) } : r)));
        }}
      />
      <SearchablePicker
        open={picker?.type === 'bulk-leader'}
        onClose={() => setPicker(null)}
        kind="lecturer"
        title="Leader lecturer"
        subtitle="Optional — same as single entry"
        placeholder="Search name or email…"
        items={[{ id: '', names: '— None —' }, ...lecturers]}
        value={picker?.rowIndex != null ? bulkRows[picker.rowIndex]?.leaderLecturerId || '' : ''}
        getLabel={lecturerPickerLabel}
        getMeta={lecturerPickerMeta}
        onSelect={(u) => {
          const idx = picker?.rowIndex;
          if (idx == null) return;
          setBulkRows((p) =>
            p.map((r, i) =>
              i === idx
                ? {
                    ...r,
                    leaderLecturerId: u.id ? String(u.id) : '',
                    otherLecturerIds: (r.otherLecturerIds || []).filter(
                      (id) => !u.id || Number(id) !== Number(u.id)
                    ),
                  }
                : r
            )
          );
        }}
      />
      <SearchablePicker
        open={picker?.type === 'bulk-others'}
        onClose={() => setPicker(null)}
        kind="lecturer"
        accent="amber"
        title="Other lecturers"
        subtitle="Multi-select — same as single entry"
        placeholder="Search lecturer name or email…"
        items={lecturers.filter((u) => {
          const idx = picker?.rowIndex;
          const leaderId = idx != null ? bulkRows[idx]?.leaderLecturerId : null;
          return !leaderId || String(u.id) !== String(leaderId);
        })}
        value={picker?.rowIndex != null ? bulkRows[picker.rowIndex]?.otherLecturerIds || [] : []}
        multiple
        getLabel={lecturerPickerLabel}
        getMeta={lecturerPickerMeta}
        onSelect={(list) => {
          const idx = picker?.rowIndex;
          if (idx == null) return;
          setBulkRows((p) =>
            p.map((r, i) => (i === idx ? { ...r, otherLecturerIds: list.map((u) => u.id) } : r))
          );
        }}
        confirmLabel="Apply lecturers"
      />
      <SearchablePicker
        open={picker?.type === 'upload-module'}
        onClose={() => setPicker(null)}
        kind="module"
        title="Choose module"
        placeholder="Search module code or name…"
        items={(() => {
          if (picker?.sectionIndex == null || picker?.rowIndex == null) return programModules.length ? programModules : modules;
          const row = matchedSections[picker.sectionIndex]?.rows?.find((x) => x.rowIndex === picker.rowIndex);
          const candidates = row?.moduleCandidates || [];
          if (candidates.length) return candidates;
          const pid = matchedSections[picker.sectionIndex]?.program?.id;
          if (pid) return modules.filter((m) => String(m.programId) === String(pid));
          return modules;
        })()}
        value={(() => {
          if (picker?.sectionIndex == null || picker?.rowIndex == null) return null;
          const row = matchedSections[picker.sectionIndex]?.rows?.find((x) => x.rowIndex === picker.rowIndex);
          return row?.module?.id || null;
        })()}
        getLabel={(m) => `${m.code ? `${m.code} — ` : ''}${m.name}`}
        getMeta={(m) => `Year ${m.year ?? '—'} · Sem ${m.semester ?? '—'}`}
        onSelect={(m) => {
          const si = picker?.sectionIndex;
          const ri = picker?.rowIndex;
          if (si == null || ri == null) return;
          patchMatchedRow(si, ri, { module: m });
        }}
      />
      <SearchablePicker
        open={picker?.type === 'upload-facility'}
        onClose={() => {
          setPicker(null);
          setUploadFacilityChoices([]);
          setUploadFacilityLoading(false);
        }}
        kind="facility"
        title={uploadFacilityLoading ? 'Loading free facilities…' : 'Choose free facility'}
        subtitle={
          uploadFacilityLoading
            ? 'Checking timetable + this Excel for the row’s day/time…'
            : 'Only available rooms for this day/time · name (seats) · building'
        }
        placeholder="Search room, building, site, campus…"
        emptyText={
          uploadFacilityLoading
            ? 'Loading…'
            : 'No free rooms for this day/time — every facility is taken (saved timetable or another Excel row), or none fit the group size'
        }
        items={uploadFacilityLoading ? [] : uploadFacilityChoices}
        value={(() => {
          if (picker?.sectionIndex == null || picker?.rowIndex == null) return null;
          const row = matchedSections[picker.sectionIndex]?.rows?.find((x) => x.rowIndex === picker.rowIndex);
          return row?.facility?.id || null;
        })()}
        getLabel={facilityPickerLabel}
        getMeta={facilityPickerMeta}
        filterItem={(f, q) => facilitySearchHaystack(f).includes(q)}
        onSelect={(f) => {
          const si = picker?.sectionIndex;
          const ri = picker?.rowIndex;
          if (si == null || ri == null) return;
          patchMatchedRow(si, ri, {
            facility: {
              id: f.id,
              name: f.name,
              name2: f.name2,
              capacity: f.capacity,
              type: f.type,
              site: f.site,
              buildName: f.buildName,
              buildCode: f.buildCode,
              campus: f.campus?.name || (typeof f.campus === 'string' ? f.campus : null),
            },
            warnings: quietRowWarnings(
              matchedSections[si]?.rows?.find((x) => x.rowIndex === ri)?.warnings
            ),
          });
        }}
      />
      <SearchablePicker
        open={picker?.type === 'upload-leader'}
        onClose={() => setPicker(null)}
        kind="lecturer"
        title={picker?.excelName ? `Assign leader` : 'Leader lecturer'}
        subtitle={picker?.excelName ? `Excel name: “${picker.excelName}”` : 'Search system users'}
        placeholder="Search name or email…"
        items={[{ id: '', names: '— None —' }, ...lecturers]}
        value={(() => {
          if (picker?.sectionIndex == null || picker?.rowIndex == null) return null;
          const row = matchedSections[picker.sectionIndex]?.rows?.find(
            (x) => x.rowIndex === picker.rowIndex
          );
          return row?.lecturers?.leader?.id || '';
        })()}
        getLabel={lecturerPickerLabel}
        getMeta={lecturerPickerMeta}
        onSelect={(u) => {
          const si = picker?.sectionIndex;
          const ri = picker?.rowIndex;
          if (si == null || ri == null) return;
          const row = matchedSections[si]?.rows?.find((x) => x.rowIndex === ri);
          if (!u.id) {
            patchMatchedRow(si, ri, {
              lecturers: { leader: null, others: row?.lecturers?.others || [] },
            });
            return;
          }
          patchMatchedRow(si, ri, {
            lecturers: {
              leader: {
                id: u.id,
                names: capitalizePersonName(u.names),
                urEmail: u.urEmail,
                email: u.email,
              },
              others: (row?.lecturers?.others || []).filter((o) => Number(o.id) !== Number(u.id)),
            },
            missedLecturers: (row?.missedLecturers || []).filter(
              (n) => !picker?.excelName || String(n) !== String(picker.excelName)
            ),
          });
        }}
      />
      <SearchablePicker
        open={picker?.type === 'upload-others'}
        onClose={() => setPicker(null)}
        kind="lecturer"
        accent="amber"
        title="Other lecturers"
        subtitle="Multi-select · amber chips show who is assigned"
        placeholder="Search lecturer name or email…"
        items={lecturers}
        multiple
        value={(() => {
          if (picker?.sectionIndex == null || picker?.rowIndex == null) return [];
          const row = matchedSections[picker.sectionIndex]?.rows?.find(
            (x) => x.rowIndex === picker.rowIndex
          );
          return (row?.lecturers?.others || []).map((o) => o.id);
        })()}
        getLabel={lecturerPickerLabel}
        getMeta={lecturerPickerMeta}
        onSelect={(list) => {
          const si = picker?.sectionIndex;
          const ri = picker?.rowIndex;
          if (si == null || ri == null) return;
          const row = matchedSections[si]?.rows?.find((x) => x.rowIndex === ri);
          const leader = row?.lecturers?.leader || null;
          const others = list
            .filter((u) => !leader || Number(u.id) !== Number(leader.id))
            .map((u) => ({
              id: u.id,
              names: capitalizePersonName(u.names),
              urEmail: u.urEmail,
              email: u.email,
            }));
          patchMatchedRow(si, ri, {
            lecturers: { leader, others },
          });
        }}
        confirmLabel="Apply other lecturers"
      />
      <SearchablePicker
        open={picker?.type === 'upload-resolve-missed'}
        onClose={() => setPicker(null)}
        kind="lecturer"
        title="Match Excel name to system user"
        subtitle={picker?.excelName ? `Looking up “${picker.excelName}”` : ''}
        placeholder="Search name or email…"
        items={lecturers}
        initialQuery={String(picker?.excelName || '')
          .replace(/\([^)]*\)/g, ' ')
          .replace(/\b(prof\.?|dr\.?|mr\.?|mrs\.?|ms\.?)\b/gi, ' ')
          .trim()}
        getLabel={lecturerPickerLabel}
        getMeta={lecturerPickerMeta}
        onSelect={(u) => {
          const si = picker?.sectionIndex;
          const ri = picker?.rowIndex;
          const excelName = picker?.excelName;
          if (si == null || ri == null) return;
          const row = matchedSections[si]?.rows?.find((x) => x.rowIndex === ri);
          const dto = {
            id: u.id,
            names: capitalizePersonName(u.names),
            urEmail: u.urEmail,
            email: u.email,
          };
          let leader = row?.lecturers?.leader || null;
          let others = [...(row?.lecturers?.others || [])];
          if (!leader) leader = dto;
          else if (Number(leader.id) !== Number(u.id) && !others.some((o) => Number(o.id) === Number(u.id))) {
            others.push(dto);
          }
          patchMatchedRow(si, ri, {
            lecturers: { leader, others },
            missedLecturers: (row?.missedLecturers || []).filter((n) => String(n) !== String(excelName)),
          });
        }}
      />
    </div>
  );
}
