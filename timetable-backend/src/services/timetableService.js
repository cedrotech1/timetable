import { Op } from "sequelize";
import db from "../database/models/index.js";
import { normalizeTime, timesOverlapStr } from "../utils/timeFormat.js";

const {
  Timetable,
  TimetableSession,
  TimetableGroup,
  TimetableLecturer,
  StudentGroup,
  Module,
  Facility,
  Users,
  AcademicYear,
  TimetableSettings,
  Intake,
  Campus,
  Program,
  School,
  College,
  sequelize,
} = db;

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

function timesOverlap(aStart, aEnd, bStart, bEnd) {
  return timesOverlapStr(aStart, aEnd, bStart, bEnd);
}

export function normalizeSessions(sessions = []) {
  const out = [];
  for (const s of sessions) {
    const day = String(s.day || "").trim();
    const start = normalizeTime(s.start || s.startTime);
    const end = normalizeTime(s.end || s.endTime);
    if (!day || !start || !end) continue;
    if (start >= end) {
      const err = new Error(`Invalid time range for ${day}: ${s.start || s.startTime} - ${s.end || s.endTime}`);
      err.code = "INVALID_SESSION";
      throw err;
    }
    out.push({ day, startTime: start, endTime: end });
  }
  return out;
}

/**
 * Facility + group conflicts only. Lecturer overlaps are intentionally ignored (PHP behavior).
 * Same facility + overlapping time = always a facility conflict.
 * (Combined classes must be ONE plan with multiple groups — not two plans in the same room.)
 */
export async function findConflicts({
  facilityId,
  moduleId,
  academicYearId,
  semester,
  groupIds,
  sessions,
  excludeTimetableId = null,
  batchBooked = [],
}) {
  const conflicts = { facility: [], groups: {} };

  const existingPlans = await Timetable.findAll({
    where: {
      academicYearId,
      semester: String(semester),
      [Op.or]: [
        { status: "Approved" },
        { status: "approved" },
        { status: "pending" },
        { status: "Pending" },
      ],
      ...(excludeTimetableId ? { id: { [Op.ne]: excludeTimetableId } } : {}),
    },
    include: [
      { model: Module, as: "module", attributes: ["id", "code", "name"] },
      { model: Facility, as: "facility", attributes: ["id", "name", "capacity"] },
      { model: TimetableSession, as: "sessions", required: true },
      {
        model: TimetableGroup,
        as: "timetableGroups",
        required: false,
        include: [{ model: StudentGroup, as: "group", attributes: ["id", "name", "size"] }],
      },
    ],
  });

  const groupIdSet = new Set((groupIds || []).map(Number));
  const groupNameById = {};

  const fmtGroups = (planGroups) =>
    (planGroups || [])
      .map((tg) => tg.group?.name || `Group#${tg.groupId}`)
      .filter(Boolean)
      .join(", ");

  for (const plan of existingPlans) {
    const planGroupIds = (plan.timetableGroups || []).map((g) => Number(g.groupId));
    for (const tg of plan.timetableGroups || []) {
      if (tg.group?.name) groupNameById[Number(tg.groupId)] = tg.group.name;
    }
    const sharedGroups = planGroupIds.filter((g) => groupIdSet.has(g));
    const sameFacility = Number(plan.facilityId) === Number(facilityId);
    const groupsLabel = fmtGroups(plan.timetableGroups);

    for (const sess of plan.sessions || []) {
      for (const neu of sessions) {
        if (String(sess.day) !== String(neu.day)) continue;
        if (!timesOverlap(sess.startTime, sess.endTime, neu.startTime, neu.endTime)) continue;

        if (sameFacility) {
          conflicts.facility.push({
            timetableId: plan.id,
            day: sess.day,
            startTime: sess.startTime,
            endTime: sess.endTime,
            moduleId: plan.moduleId,
            moduleCode: plan.module?.code || null,
            moduleName: plan.module?.name || null,
            facilityId: plan.facilityId,
            facilityName: plan.facility?.name || null,
            facilityCapacity: plan.facility?.capacity ?? null,
            groupsLabel,
            source: "saved",
            kind: "facility",
            simpleReason: `ROOM conflict: room already used by ${plan.module?.code || "another class"} (${groupsLabel || "saved plan"})`,
          });
        }

        for (const gid of sharedGroups) {
          if (!conflicts.groups[gid]) conflicts.groups[gid] = [];
          conflicts.groups[gid].push({
            timetableId: plan.id,
            groupId: gid,
            groupName: groupNameById[gid] || `Group#${gid}`,
            day: sess.day,
            startTime: sess.startTime,
            endTime: sess.endTime,
            moduleCode: plan.module?.code || null,
            moduleName: plan.module?.name || null,
            facilityName: plan.facility?.name || null,
            groupsLabel,
            source: "saved",
            kind: "group",
            simpleReason: `GROUP conflict: this group already has a saved class at this time`,
          });
        }
      }
    }
  }

  // Within-batch overlaps (for bulk)
  for (const booked of batchBooked) {
    const bookedGroups = new Set((booked.groupIds || []).map(Number));
    const shared = [...groupIdSet].filter((g) => bookedGroups.has(g));
    const sameFacility = Number(booked.facilityId) === Number(facilityId);
    const groupsLabel = (booked.groupNames || []).join(", ") || (booked.groupIds || []).map((g) => `Group#${g}`).join(", ");

    for (const bSess of booked.sessions || []) {
      for (const neu of sessions) {
        if (String(bSess.day) !== String(neu.day)) continue;
        if (!timesOverlap(bSess.startTime, bSess.endTime, neu.startTime, neu.endTime)) continue;

        if (sameFacility) {
          conflicts.facility.push({
            timetableId: booked.tempId || null,
            day: bSess.day,
            startTime: bSess.startTime,
            endTime: bSess.endTime,
            moduleId: booked.moduleId,
            moduleCode: booked.moduleCode || null,
            moduleName: booked.moduleName || null,
            facilityId: booked.facilityId,
            facilityName: booked.facilityName || null,
            groupsLabel,
            source: "batch",
            simpleReason: `ROOM conflict: same room already taken by another Excel row in this import`,
          });
        }
        for (const gid of shared) {
          if (!conflicts.groups[gid]) conflicts.groups[gid] = [];
          conflicts.groups[gid].push({
            timetableId: booked.tempId || null,
            groupId: gid,
            groupName: booked.groupNameById?.[gid] || `Group#${gid}`,
            day: bSess.day,
            startTime: bSess.startTime,
            endTime: bSess.endTime,
            moduleCode: booked.moduleCode || null,
            moduleName: booked.moduleName || null,
            facilityName: booked.facilityName || null,
            groupsLabel,
            source: "batch",
            kind: "group",
            simpleReason: `GROUP conflict: this group already appears at this time on another Excel row (often a duplicate with a different room — remove or merge that row)`,
          });
        }
      }
    }
  }

  const hasConflict =
    conflicts.facility.length > 0 || Object.keys(conflicts.groups).length > 0;

  return { hasConflict, conflicts, conflictKinds: getConflictKinds(conflicts) };
}

export function getConflictKinds(conflicts) {
  if (!conflicts) return [];
  const kinds = [];
  if ((conflicts.facility || []).length > 0) kinds.push("facility");
  if (Object.keys(conflicts.groups || {}).length > 0) kinds.push("group");
  return kinds;
}

export function conflictErrorMessage(kinds) {
  const set = new Set(kinds || []);
  if (set.has("facility") && set.has("group")) {
    return "ROOM conflict and GROUP time conflict — see details below.";
  }
  if (set.has("facility")) {
    return "ROOM / facility conflict — this room is already used at that time.";
  }
  if (set.has("group")) {
    return "GROUP time conflict — these student group(s) already have a class at this time (often a duplicate Excel row with another room).";
  }
  return "Conflicts detected (facility or groups).";
}

export async function getActiveSettings() {
  const settings = await TimetableSettings.findOne({
    where: { status: "live" },
    include: [{ model: AcademicYear, as: "academicYear" }],
    order: [["id", "ASC"]],
  });
  if (settings) return settings;

  // Fallback: first academic year + semester 1
  const ay = await AcademicYear.findOne({ order: [["id", "ASC"]] });
  if (!ay) return null;
  return {
    academicYearId: ay.id,
    semester: "1",
    academicYear: ay,
    status: "live",
  };
}

/**
 * Shared create used by single, bulk, and upload.
 */
export async function createTeachingPlan(dto, { user, transaction: outerTx = null, batchBooked = [] } = {}) {
  const moduleId = Number(dto.moduleId || dto.selectedModuleId);
  const facilityId = Number(dto.facilityId || dto.selectedFacilityId);
  const academicYearId = Number(dto.academicYearId);
  const semester = String(dto.semester ?? "");
  const groupIds = (dto.groupIds || dto.selectedGroupIds || []).map(Number).filter(Boolean);
  const leaderLecturerId = Number(dto.leaderLecturerId || dto.moduleLeaderId || 0) || null;
  const otherLecturerIds = (dto.otherLecturerIds || dto.lecturerIds || [])
    .map(Number)
    .filter((id) => id && id !== leaderLecturerId);
  const ignoreConflicts = Boolean(dto.ignoreConflicts);
  const dryRun = Boolean(dto.dryRun);

  const missing = [];
  if (!moduleId) missing.push("module");
  if (!facilityId) missing.push("facility");
  if (!academicYearId) missing.push("academic year");
  if (!semester) missing.push("semester");
  if (!groupIds.length) missing.push("groups");
  if (missing.length) {
    const err = new Error(`Missing required fields: ${missing.join(", ")}`);
    err.code = "VALIDATION";
    err.missing = missing;
    throw err;
  }

  const sessions = normalizeSessions(dto.sessions || dto.schedule || []);
  if (!sessions.length) {
    const err = new Error("Please add at least one session time.");
    err.code = "VALIDATION";
    throw err;
  }

  // Ensure entities exist
  const [mod, fac, groups] = await Promise.all([
    Module.findByPk(moduleId),
    Facility.findByPk(facilityId),
    StudentGroup.findAll({ where: { id: groupIds } }),
  ]);
  if (!mod) {
    const err = new Error("Module not found");
    err.code = "NOT_FOUND";
    throw err;
  }
  if (!fac) {
    const err = new Error("Facility not found");
    err.code = "NOT_FOUND";
    throw err;
  }
  if (groups.length !== groupIds.length) {
    const err = new Error("One or more groups were not found");
    err.code = "NOT_FOUND";
    throw err;
  }

  const { hasConflict, conflicts, conflictKinds } = await findConflicts({
    facilityId,
    moduleId,
    academicYearId,
    semester,
    groupIds,
    sessions,
    batchBooked,
  });

  if (hasConflict && !ignoreConflicts) {
    const err = new Error(conflictErrorMessage(conflictKinds));
    err.code = "CONFLICT";
    err.conflicts = conflicts;
    err.conflictKinds = conflictKinds;
    throw err;
  }

  if (dryRun) {
    return { dryRun: true, ok: true, conflicts: hasConflict ? conflicts : null };
  }

  const role = user?.role || "";
  const autoApprove = ["admin", "registrar_office"].includes(role);
  const status = autoApprove ? "Approved" : "pending";

  const run = async (t) => {
    const plan = await Timetable.create(
      {
        moduleId,
        facilityId,
        academicYearId,
        semester,
        leaderLecturerId,
        status,
        approvedBy: autoApprove ? user.id : null,
        createdBy: user.id,
      },
      { transaction: t }
    );

    await TimetableSession.bulkCreate(
      sessions.map((s) => ({
        timetableId: plan.id,
        day: s.day,
        startTime: s.startTime,
        endTime: s.endTime,
      })),
      { transaction: t }
    );

    await TimetableGroup.bulkCreate(
      groupIds.map((groupId) => ({ timetableId: plan.id, groupId })),
      { transaction: t }
    );

    if (otherLecturerIds.length) {
      await TimetableLecturer.bulkCreate(
        otherLecturerIds.map((lecturerId) => ({ timetableId: plan.id, lecturerId })),
        { transaction: t }
      );
    }

    return plan;
  };

  const plan = outerTx ? await run(outerTx) : await sequelize.transaction(run);

  return {
    ok: true,
    id: plan.id,
    status: plan.status,
    conflictsIgnored: hasConflict && ignoreConflicts ? conflicts : null,
  };
}

/**
 * Update an existing teaching plan. Same validation + facility/group conflict rules as create,
 * excluding this plan from conflict checks.
 */
export async function updateTeachingPlan(id, dto, { user } = {}) {
  const planId = Number(id);
  const existing = await Timetable.findByPk(planId);
  if (!existing) {
    const err = new Error("Teaching plan not found");
    err.code = "NOT_FOUND";
    throw err;
  }

  const moduleId = Number(dto.moduleId || dto.selectedModuleId || existing.moduleId);
  const facilityId = Number(dto.facilityId || dto.selectedFacilityId || existing.facilityId);
  const academicYearId = Number(dto.academicYearId || existing.academicYearId);
  const semester = String(dto.semester ?? existing.semester ?? "");
  const groupIds = (dto.groupIds || dto.selectedGroupIds || []).map(Number).filter(Boolean);
  const leaderLecturerId =
    dto.leaderLecturerId !== undefined || dto.moduleLeaderId !== undefined
      ? Number(dto.leaderLecturerId || dto.moduleLeaderId || 0) || null
      : existing.leaderLecturerId;
  const otherLecturerIds = (dto.otherLecturerIds || dto.lecturerIds || [])
    .map(Number)
    .filter((lid) => lid && lid !== leaderLecturerId);
  const ignoreConflicts = Boolean(dto.ignoreConflicts);

  const missing = [];
  if (!moduleId) missing.push("module");
  if (!facilityId) missing.push("facility");
  if (!academicYearId) missing.push("academic year");
  if (!semester) missing.push("semester");
  if (!groupIds.length) missing.push("groups");
  if (missing.length) {
    const err = new Error(`Missing required fields: ${missing.join(", ")}`);
    err.code = "VALIDATION";
    err.missing = missing;
    throw err;
  }

  const sessions = normalizeSessions(dto.sessions || dto.schedule || []);
  if (!sessions.length) {
    const err = new Error("Please add at least one session time.");
    err.code = "VALIDATION";
    throw err;
  }

  const [mod, fac, groups] = await Promise.all([
    Module.findByPk(moduleId),
    Facility.findByPk(facilityId),
    StudentGroup.findAll({ where: { id: groupIds } }),
  ]);
  if (!mod) {
    const err = new Error("Module not found");
    err.code = "NOT_FOUND";
    throw err;
  }
  if (!fac) {
    const err = new Error("Facility not found");
    err.code = "NOT_FOUND";
    throw err;
  }
  if (groups.length !== groupIds.length) {
    const err = new Error("One or more groups were not found");
    err.code = "NOT_FOUND";
    throw err;
  }

  const { hasConflict, conflicts, conflictKinds } = await findConflicts({
    facilityId,
    moduleId,
    academicYearId,
    semester,
    groupIds,
    sessions,
    excludeTimetableId: planId,
  });

  if (hasConflict && !ignoreConflicts) {
    const err = new Error(conflictErrorMessage(conflictKinds));
    err.code = "CONFLICT";
    err.conflicts = conflicts;
    err.conflictKinds = conflictKinds;
    throw err;
  }

  const status =
    dto.status != null && String(dto.status).trim()
      ? String(dto.status).trim()
      : existing.status;

  await sequelize.transaction(async (t) => {
    await existing.update(
      {
        moduleId,
        facilityId,
        academicYearId,
        semester,
        leaderLecturerId,
        status,
        approvedBy:
          ["Approved", "approved"].includes(status) && !existing.approvedBy
            ? user?.id || existing.approvedBy
            : existing.approvedBy,
      },
      { transaction: t }
    );

    await TimetableSession.destroy({ where: { timetableId: planId }, transaction: t });
    await TimetableSession.bulkCreate(
      sessions.map((s) => ({
        timetableId: planId,
        day: s.day,
        startTime: s.startTime,
        endTime: s.endTime,
      })),
      { transaction: t }
    );

    await TimetableGroup.destroy({ where: { timetableId: planId }, transaction: t });
    await TimetableGroup.bulkCreate(
      groupIds.map((groupId) => ({ timetableId: planId, groupId })),
      { transaction: t }
    );

    await TimetableLecturer.destroy({ where: { timetableId: planId }, transaction: t });
    if (otherLecturerIds.length) {
      await TimetableLecturer.bulkCreate(
        otherLecturerIds.map((lecturerId) => ({ timetableId: planId, lecturerId })),
        { transaction: t }
      );
    }
  });

  return {
    ok: true,
    id: planId,
    status,
    conflictsIgnored: hasConflict && ignoreConflicts ? conflicts : null,
  };
}

export async function deleteTeachingPlan(id) {
  const planId = Number(id);
  const existing = await Timetable.findByPk(planId);
  if (!existing) {
    const err = new Error("Teaching plan not found");
    err.code = "NOT_FOUND";
    throw err;
  }
  await sequelize.transaction(async (t) => {
    await TimetableSession.destroy({ where: { timetableId: planId }, transaction: t });
    await TimetableGroup.destroy({ where: { timetableId: planId }, transaction: t });
    await TimetableLecturer.destroy({ where: { timetableId: planId }, transaction: t });
    await existing.destroy({ transaction: t });
  });
  return { ok: true, id: planId };
}

export async function createTeachingPlansBulk({ academicYearId, semester, groupIds, rows, dryRun, ignoreConflicts }, user) {
  const results = [];
  const batchBooked = [];
  const sharedGroups = (groupIds || []).map(Number).filter(Boolean);

  for (let i = 0; i < rows.length; i += 1) {
    const row = rows[i];
    const rowGroups = (row.groupIds || row.group_ids || sharedGroups).map(Number).filter(Boolean);
    try {
      // Per-row transaction so one conflict/error cannot abort later rows (PHP partial save)
      const result = await createTeachingPlan(
        {
          ...row,
          moduleId: row.moduleId || row.module_id,
          facilityId: row.facilityId || row.facility_id,
          leaderLecturerId: row.leaderLecturerId || row.leader_id || row.moduleLeaderId,
          otherLecturerIds: row.otherLecturerIds || row.other_lecturer_ids,
          sessions: row.sessions || (row.day ? [{ day: row.day, start: row.start, end: row.end }] : row.schedule),
          groupIds: rowGroups,
          academicYearId,
          semester,
          dryRun,
          ignoreConflicts,
        },
        { user, batchBooked }
      );

      const sessions = normalizeSessions(
        row.sessions || (row.day ? [{ day: row.day, start: row.start, end: row.end }] : row.schedule) || []
      );
      const groupNames = (row.groupNames || []).filter(Boolean);
      batchBooked.push({
        tempId: `row-${i + 1}`,
        moduleId: Number(row.moduleId || row.module_id),
        moduleCode: row.moduleCode || row.module_code || null,
        moduleName: row.moduleName || row.module_name || null,
        facilityId: Number(row.facilityId || row.facility_id),
        facilityName: row.facilityName || row.facility_name || null,
        groupIds: rowGroups,
        groupNames,
        groupNameById: row.groupNameById || null,
        sessions,
      });

      results.push({
        index: i,
        success: true,
        ...result,
        attempt: {
          day: sessions[0]?.day,
          start: sessions[0]?.startTime,
          end: sessions[0]?.endTime,
          moduleCode: row.moduleCode || null,
          moduleName: row.moduleName || null,
          facilityName: row.facilityName || null,
          groupsLabel: groupNames.join(", ") || rowGroups.join(", "),
        },
      });
    } catch (error) {
      const sessions = normalizeSessions(
        row.sessions || (row.day ? [{ day: row.day, start: row.start, end: row.end }] : row.schedule) || []
      );
      results.push({
        index: i,
        success: false,
        code: error.code || "ERROR",
        message: error.message,
        conflicts: error.conflicts || null,
        conflictKinds: error.conflictKinds || getConflictKinds(error.conflicts),
        attempt: {
          day: sessions[0]?.day || row.day,
          start: sessions[0]?.startTime || row.start,
          end: sessions[0]?.endTime || row.end,
          moduleCode: row.moduleCode || null,
          moduleName: row.moduleName || null,
          facilityName: row.facilityName || null,
          groupsLabel:
            (row.groupNames || []).filter(Boolean).join(", ") ||
            (row.groupIds || rowGroups || []).join(", "),
        },
      });
    }
  }

  const failed = results.filter((r) => !r.success);
  return {
    dryRun: Boolean(dryRun),
    results,
    failed: failed.length,
    saved: dryRun ? 0 : results.filter((r) => r.success).length,
  };
}

export async function listTimetables({ academicYearId, semester, status } = {}) {
  const where = {};
  if (academicYearId) where.academicYearId = academicYearId;
  if (semester) where.semester = String(semester);
  if (status) where.status = status;

  const rows = await Timetable.findAll({
    where,
    include: timetableListInclude(),
    order: [["id", "ASC"]],
  });

  return rows.map(shapeTimetableRow);
}

export async function getTimetableById(id) {
  const planId = Number(id);
  if (!planId) {
    const err = new Error("Invalid teaching plan id");
    err.code = "VALIDATION";
    throw err;
  }
  const row = await Timetable.findByPk(planId, { include: timetableListInclude() });
  if (!row) {
    const err = new Error("Teaching plan not found");
    err.code = "NOT_FOUND";
    throw err;
  }
  return shapeTimetableRow(row);
}

function timetableListInclude() {
  return [
    { model: Module, as: "module", include: [{ model: Program, as: "program" }] },
    { model: Facility, as: "facility", include: [{ model: Campus, as: "campus" }] },
    { model: AcademicYear, as: "academicYear" },
    { model: Users, as: "leader", attributes: { exclude: ["password", "resetcode"] } },
    { model: TimetableSession, as: "sessions" },
    {
      model: TimetableGroup,
      as: "timetableGroups",
      include: [
        {
          model: StudentGroup,
          as: "group",
          include: [
            {
              model: Intake,
              as: "intake",
              include: [
                { model: Campus, as: "campus" },
                {
                  model: Program,
                  as: "program",
                  include: [
                    {
                      model: School,
                      as: "school",
                      include: [{ model: College, as: "college" }],
                    },
                  ],
                },
              ],
            },
          ],
        },
      ],
    },
    {
      model: TimetableLecturer,
      as: "timetableLecturers",
      include: [{ model: Users, as: "lecturer", attributes: { exclude: ["password", "resetcode"] } }],
    },
  ];
}

/** Shape compatible with PHP get_timetable.php / general_timetable.php */
function shapeTimetableRow(t) {
  const json = typeof t.toJSON === "function" ? t.toJSON() : t;
  const groups = (json.timetableGroups || [])
    .map((tg) => {
      const g = tg.group;
      if (!g) return null;
      const intake = g.intake || {};
      const program = intake.program || {};
      const school = program.school || {};
      const college = school.college || {};
      return {
        id: g.id,
        name: g.name,
        size: g.size,
        year_of_study: intake.yearOfStudy,
        program: program.name || null,
        program_id: program.id || null,
        school: school.name || null,
        school_id: school.id || null,
        college: college.name || null,
        college_id: college.id || null,
        campus: intake.campus?.name || null,
        campus_id: intake.campus?.id || intake.campusId || null,
        intake_id: intake.id || null,
      };
    })
    .filter(Boolean);

  return {
    id: json.id,
    status: json.status,
    semester: json.semester,
    academicYear: json.academicYear,
    course: json.module?.name || null,
    code: json.module?.code || null,
    credits: json.module?.credits ?? null,
    module: json.module,
    facility: json.facility
      ? {
          id: json.facility.id,
          name: json.facility.name,
          name2: json.facility.name2 || null,
          type: json.facility.type,
          capacity: json.facility.capacity,
          buildName: json.facility.buildName || null,
          buildCode: json.facility.buildCode || null,
          campus: json.facility.campus,
          site: json.facility.site ? { name: json.facility.site } : null,
        }
      : null,
    leader_lecturer: json.leader
      ? {
          id: json.leader.id,
          names: json.leader.names,
          email: json.leader.email || json.leader.urEmail,
          phone: json.leader.phone,
        }
      : null,
    leader: json.leader,
    other_lecturers: (json.timetableLecturers || [])
      .map((tl) => tl.lecturer)
      .filter(Boolean)
      .map((l) => ({
        id: l.id,
        names: l.names,
        email: l.email || l.urEmail,
        phone: l.phone,
      })),
    otherLecturers: (json.timetableLecturers || []).map((tl) => tl.lecturer).filter(Boolean),
    sessions: (json.sessions || []).map((s) => ({
      id: s.id,
      day: s.day,
      start_time: s.startTime,
      end_time: s.endTime,
      startTime: s.startTime,
      endTime: s.endTime,
    })),
    groups,
    createdBy: json.createdBy,
    approvedBy: json.approvedBy,
    createdAt: json.createdAt,
    updatedAt: json.updatedAt,
  };
}

export async function getAvailableFacilities({ academicYearId, semester, sessions, minCapacity = 0, excludeTimetableId = null }) {
  const norm = normalizeSessions(sessions);
  const facilities = await Facility.findAll({
    include: [{ model: Campus, as: "campus" }],
    order: [["name", "ASC"]],
  });

  const busy = await Timetable.findAll({
    where: {
      academicYearId,
      semester: String(semester),
      [Op.or]: [{ status: "Approved" }, { status: "approved" }, { status: "pending" }, { status: "Pending" }],
      ...(excludeTimetableId ? { id: { [Op.ne]: excludeTimetableId } } : {}),
    },
    include: [{ model: TimetableSession, as: "sessions", required: true }],
  });

  const busyFacilityIds = new Set();
  for (const plan of busy) {
    for (const sess of plan.sessions || []) {
      for (const neu of norm) {
        if (sess.day === neu.day && timesOverlap(sess.startTime, sess.endTime, neu.startTime, neu.endTime)) {
          busyFacilityIds.add(plan.facilityId);
        }
      }
    }
  }

  return facilities.filter((f) => {
    if (busyFacilityIds.has(f.id)) return false;
    if (minCapacity && f.capacity != null && f.capacity < minCapacity) return false;
    return true;
  });
}

export { DAYS };
