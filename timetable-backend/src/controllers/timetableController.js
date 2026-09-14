import {
  createTeachingPlan,
  createTeachingPlansBulk,
  listTimetables,
  getTimetableById,
  updateTeachingPlan,
  deleteTeachingPlan,
  getAvailableFacilities,
  getActiveSettings,
  DAYS,
} from "../services/timetableService.js";
import { matchImportSections, createIntakeGroups, createIntakeGroupsBulk, autoAssignImportFacilities, getFacilityCalendar } from "../services/excelImportService.js";
import {
  getSystemCounts,
  updateSystemSettings,
  createAcademicYear,
  deleteAcademicYear,
  resetAllTimetables,
  clearIntakesAndGroups,
} from "../services/systemSettingsService.js";
import { logSuccess, logFailure } from "../services/logService.js";
import db from "../database/models/index.js";

const { AcademicYear, TimetableSettings, Intake, StudentGroup, Program, Campus, School, College, Users } = db;

export const getSettingsController = async (req, res) => {
  try {
    const settings = await getActiveSettings();
    const years = await AcademicYear.findAll({ order: [["id", "DESC"]] });
    const counts = await getSystemCounts();
    return res.status(200).json({
      success: true,
      data: {
        settings,
        academicYears: years,
        counts,
        days: DAYS,
        timeBlocks: [
          { id: "morning", label: "Morning", start: "08:00", end: "13:00" },
          { id: "afternoon", label: "Afternoon", start: "14:00", end: "17:00" },
          { id: "evening", label: "Evening", start: "17:00", end: "21:00" },
        ],
      },
    });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const updateSettingsController = async (req, res) => {
  try {
    const data = await updateSystemSettings(
      {
        status: req.body.status,
        academicYearId: req.body.academicYearId || req.body.academic_year_id,
        semester: req.body.semester,
      },
      req.user
    );
    logSuccess(req, "Updated system settings", "Settings", "UPDATE", null, data?.id, "TimetableSettings");
    return res.status(200).json({ success: true, message: "System settings updated", data });
  } catch (error) {
    const status = error.code === "NOT_FOUND" || error.code === "VALIDATION" ? 400 : 500;
    return res.status(status).json({ success: false, message: error.message });
  }
};

export const createAcademicYearController = async (req, res) => {
  try {
    const data = await createAcademicYear(req.body.yearLabel || req.body.year_label);
    return res.status(201).json({ success: true, message: "Academic year added", data });
  } catch (error) {
    const status = error.code === "VALIDATION" ? 400 : 500;
    return res.status(status).json({ success: false, message: error.message });
  }
};

export const deleteAcademicYearController = async (req, res) => {
  try {
    await deleteAcademicYear(req.params.id);
    return res.status(200).json({ success: true, message: "Academic year deleted" });
  } catch (error) {
    const status = error.code === "VALIDATION" || error.code === "NOT_FOUND" ? 400 : 500;
    return res.status(status).json({ success: false, message: error.message });
  }
};

export const resetTimetablesController = async (req, res) => {
  try {
    const data = await resetAllTimetables();
    logSuccess(req, "Reset all timetables", "Settings", "DELETE", null, null, "Timetable");
    return res.status(200).json({ success: true, message: data.message, data });
  } catch (error) {
    logFailure(req, "Failed to reset timetables", "Settings", "DELETE", error.message);
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const clearIntakesGroupsController = async (req, res) => {
  try {
    const data = await clearIntakesAndGroups();
    logSuccess(req, "Cleared intakes and groups", "Settings", "DELETE", null, null, "Intake");
    return res.status(200).json({ success: true, message: data.message, data });
  } catch (error) {
    logFailure(req, "Failed to clear intakes/groups", "Settings", "DELETE", error.message);
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const listTimetablesController = async (req, res) => {
  try {
    const settings = await getActiveSettings();
    const academicYearId = req.query.academicYearId || settings?.academicYearId;
    const semester = req.query.semester || settings?.semester;
    let yearLabel =
      settings?.academicYear?.yearLabel ||
      settings?.academicYear?.year_label ||
      null;
    if (!yearLabel && academicYearId) {
      const ay = await AcademicYear.findByPk(academicYearId);
      yearLabel = ay?.yearLabel || null;
    }
    const data = await listTimetables({
      academicYearId,
      semester,
      status: req.query.status,
    });
    return res.status(200).json({
      success: true,
      data,
      meta: {
        academicYearId: academicYearId != null ? Number(academicYearId) : null,
        semester,
        yearLabel,
      },
    });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const getTimetableController = async (req, res) => {
  try {
    const data = await getTimetableById(req.params.id);
    return res.status(200).json({ success: true, data });
  } catch (error) {
    if (error.code === "NOT_FOUND") {
      return res.status(404).json({ success: false, message: error.message });
    }
    if (error.code === "VALIDATION") {
      return res.status(400).json({ success: false, message: error.message });
    }
    return res.status(500).json({ success: false, message: error.message });
  }
};

/** Public student/staff browse — live AY/semester only, no auth. */
export const listPublicTimetablesController = async (req, res) => {
  try {
    const settings = await getActiveSettings();
    const academicYearId = settings?.academicYearId;
    const semester = settings?.semester;
    let yearLabel =
      settings?.academicYear?.yearLabel ||
      settings?.academicYear?.year_label ||
      null;
    if (!yearLabel && academicYearId) {
      const ay = await AcademicYear.findByPk(academicYearId);
      yearLabel = ay?.yearLabel || null;
    }
    const data = await listTimetables({
      academicYearId,
      semester,
      status: req.query.status || undefined,
    });
    // Prefer approved plans for public; fall back to all if none
    const approved = (data || []).filter((r) =>
      ["Approved", "approved"].includes(String(r.status || ""))
    );
    const rows = approved.length ? approved : data || [];
    const campuses = await Campus.findAll({ order: [["name", "ASC"]] });
    return res.status(200).json({
      success: true,
      data: rows,
      meta: {
        academicYearId: academicYearId != null ? Number(academicYearId) : null,
        semester,
        yearLabel,
        campuses,
        public: true,
      },
    });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const createTimetableController = async (req, res) => {
  try {
    const settings = await getActiveSettings();
    const payload = {
      ...req.body,
      academicYearId: req.body.academicYearId || settings?.academicYearId,
      semester: req.body.semester || settings?.semester,
    };
    const result = await createTeachingPlan(payload, { user: req.user });
    logSuccess(req, "Created timetable plan", "Timetable", "CREATE", null, result.id, "Timetable");
    return res.status(201).json({ success: true, message: "Timetable saved successfully", data: result });
  } catch (error) {
    if (error.code === "CONFLICT") {
      return res.status(409).json({
        success: false,
        status: "conflict",
        message: error.message,
        conflicts: error.conflicts,
      });
    }
    if (error.code === "VALIDATION" || error.code === "NOT_FOUND" || error.code === "INVALID_SESSION") {
      return res.status(400).json({ success: false, message: error.message, missing: error.missing });
    }
    logFailure(req, "Failed to create timetable", "Timetable", "CREATE", error.message);
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const updateTimetableController = async (req, res) => {
  try {
    const settings = await getActiveSettings();
    const payload = {
      ...req.body,
      academicYearId: req.body.academicYearId || settings?.academicYearId,
      semester: req.body.semester || settings?.semester,
    };
    const result = await updateTeachingPlan(req.params.id, payload, { user: req.user });
    logSuccess(req, "Updated timetable plan", "Timetable", "UPDATE", null, result.id, "Timetable");
    return res.status(200).json({ success: true, message: "Teaching plan updated", data: result });
  } catch (error) {
    if (error.code === "CONFLICT") {
      return res.status(409).json({
        success: false,
        status: "conflict",
        message: error.message,
        conflicts: error.conflicts,
      });
    }
    if (error.code === "VALIDATION" || error.code === "NOT_FOUND" || error.code === "INVALID_SESSION") {
      return res.status(error.code === "NOT_FOUND" ? 404 : 400).json({
        success: false,
        message: error.message,
        missing: error.missing,
      });
    }
    logFailure(req, "Failed to update timetable", "Timetable", "UPDATE", error.message);
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const deleteTimetableController = async (req, res) => {
  try {
    const result = await deleteTeachingPlan(req.params.id);
    logSuccess(req, "Deleted timetable plan", "Timetable", "DELETE", null, result.id, "Timetable");
    return res.status(200).json({ success: true, message: "Teaching plan deleted", data: result });
  } catch (error) {
    if (error.code === "NOT_FOUND") {
      return res.status(404).json({ success: false, message: error.message });
    }
    logFailure(req, "Failed to delete timetable", "Timetable", "DELETE", error.message);
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const bulkTimetableController = async (req, res) => {
  try {
    const settings = await getActiveSettings();
    const result = await createTeachingPlansBulk(
      {
        academicYearId: req.body.academicYearId || settings?.academicYearId,
        semester: req.body.semester || settings?.semester,
        groupIds: req.body.groupIds || req.body.selectedGroupIds || [],
        rows: req.body.rows || [],
        dryRun: Boolean(req.body.dryRun),
        ignoreConflicts: Boolean(req.body.ignoreConflicts),
      },
      req.user
    );
    return res.status(200).json({
      success: true,
      message: result.dryRun ? "Dry run completed" : "Bulk save completed",
      data: result,
    });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const availableFacilitiesController = async (req, res) => {
  try {
    const settings = await getActiveSettings();
    const data = await getAvailableFacilities({
      academicYearId: Number(req.query.academicYearId || req.body.academicYearId || settings?.academicYearId),
      semester: req.query.semester || req.body.semester || settings?.semester,
      sessions: req.body.sessions || req.query.sessions || [],
      minCapacity: Number(req.query.minCapacity || req.body.minCapacity || 0),
      excludeTimetableId: req.body.excludeTimetableId || req.query.excludeTimetableId || null,
    });
    return res.status(200).json({ success: true, data });
  } catch (error) {
    return res.status(400).json({ success: false, message: error.message });
  }
};

export const listIntakesController = async (req, res) => {
  try {
    const where = {};
    if (req.query.programId) where.programId = req.query.programId;
    if (req.query.campusId) where.campusId = req.query.campusId;
    if (req.query.yearOfStudy) where.yearOfStudy = req.query.yearOfStudy;

    const data = await Intake.findAll({
      where,
      include: [
        { model: Campus, as: "campus" },
        {
          model: Program,
          as: "program",
          include: [{ model: School, as: "school", include: [{ model: College, as: "college" }] }],
        },
        { model: StudentGroup, as: "groups" },
      ],
      order: [
        ["yearOfStudy", "ASC"],
        ["id", "ASC"],
      ],
    });
    return res.status(200).json({ success: true, data });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const createIntakeController = async (req, res) => {
  try {
    const { programId, campusId, yearOfStudy, size, groups } = req.body;
    if (!programId || !campusId || !yearOfStudy) {
      return res.status(400).json({
        success: false,
        message: "programId, campusId and yearOfStudy are required",
      });
    }

    const existing = await Intake.findOne({
      where: { programId, campusId, yearOfStudy },
      include: [{ model: StudentGroup, as: "groups" }],
    });
    if (existing) {
      return res.status(200).json({ success: true, message: "Intake already exists", data: existing });
    }

    const intake = await Intake.create({
      programId,
      campusId,
      yearOfStudy: Number(yearOfStudy),
      size: size || null,
      year: 0,
      month: 0,
    });

    const groupSpecs = Array.isArray(groups) && groups.length
      ? groups
      : [{ name: "Group 1", size: size || null }];

    const createdGroups = [];
    for (const g of groupSpecs) {
      const row = await StudentGroup.create({
        intakeId: intake.id,
        name: g.name || "Group 1",
        size: g.size ?? size ?? null,
      });
      createdGroups.push(row);
    }

    const full = await Intake.findByPk(intake.id, {
      include: [
        { model: Campus, as: "campus" },
        { model: Program, as: "program" },
        { model: StudentGroup, as: "groups" },
      ],
    });

    return res.status(201).json({ success: true, message: "Intake created", data: full, groups: createdGroups });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const listGroupsController = async (req, res) => {
  try {
    const where = {};
    if (req.query.intakeId) where.intakeId = req.query.intakeId;
    const data = await StudentGroup.findAll({
      where,
      include: [
        {
          model: Intake,
          as: "intake",
          include: [
            { model: Campus, as: "campus" },
            { model: Program, as: "program" },
          ],
        },
      ],
      order: [["id", "ASC"]],
    });
    return res.status(200).json({ success: true, data });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const listLecturersController = async (req, res) => {
  try {
    const { Op } = await import("sequelize");
    const data = await Users.findAll({
      where: {
        deleted: { [Op.ne]: "yes" },
        active: true,
      },
      attributes: { exclude: ["password", "resetcode"] },
      order: [["names", "ASC"]],
      limit: 5000,
    });
    return res.status(200).json({ success: true, data });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const matchImportController = async (req, res) => {
  try {
    const settings = await getActiveSettings();
    const facilityMode = req.body.facilityMode || req.body.facility_mode || "excel";
    let data = await matchImportSections({
      sections: req.body.sections || [],
      campusId: req.body.campusId || req.body.campus_id || null,
      semester: req.body.semester || settings?.semester || null,
      facilityMode,
    });

    if (facilityMode === "auto") {
      const assigned = await autoAssignImportFacilities({
        sections: data.sections,
        academicYearId: req.body.academicYearId || settings?.academicYearId,
        semester: req.body.semester || settings?.semester,
        campusId: req.body.campusId || req.body.campus_id || null,
      });
      data = { ...data, sections: assigned.sections, autoAssign: assigned.stats };
    }

    return res.status(200).json({ success: true, data });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const autoAssignFacilitiesController = async (req, res) => {
  try {
    const settings = await getActiveSettings();
    const result = await autoAssignImportFacilities({
      sections: req.body.sections || [],
      academicYearId: req.body.academicYearId || settings?.academicYearId,
      semester: req.body.semester || settings?.semester,
      campusId: req.body.campusId || req.body.campus_id || null,
    });
    return res.status(200).json({
      success: true,
      message: `Auto-assigned ${result.stats.assigned} facilities (${result.stats.failed} failed)`,
      data: result,
    });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const facilityCalendarController = async (req, res) => {
  try {
    const settings = await getActiveSettings();
    const academicYearId = req.query.academicYearId || settings?.academicYearId;
    const semester = req.query.semester || settings?.semester;
    const campusId = req.query.campusId || null;
    const data = await getFacilityCalendar({
      facilityId: req.query.facilityId || req.params.id || null,
      academicYearId,
      semester,
      campusId,
    });
    const [years, campuses] = await Promise.all([
      AcademicYear.findAll({ order: [["id", "DESC"]] }),
      Campus.findAll({ order: [["name", "ASC"]] }),
    ]);
    return res.status(200).json({
      success: true,
      data,
      meta: {
        academicYearId: Number(academicYearId) || null,
        semester: semester != null ? String(semester) : null,
        campusId: campusId ? Number(campusId) : null,
        academicYears: years,
        campuses,
        liveAcademicYearId: settings?.academicYearId,
        liveSemester: settings?.semester,
      },
    });
  } catch (error) {
    return res.status(500).json({ success: false, message: error.message });
  }
};

export const createIntakesBulkController = async (req, res) => {
  try {
    // Single PHP-style create
    if (!req.body.items) {
      const result = await createIntakeGroups({
        programId: req.body.programId || req.body.program_id,
        yearOfStudy: req.body.yearOfStudy || req.body.year_of_study,
        campusId: req.body.campusId || req.body.campus_id,
        groupNumbers: req.body.groupNumbers || req.body.group_numbers || [],
        sizeEach: req.body.sizeEach || req.body.size_each || req.body.size,
        sizeMode: req.body.sizeMode || req.body.size_mode || "each",
      });
      return res.status(201).json({
        success: true,
        message: `Promotion ready: ${result.label} (${result.createdCount} created, ${result.existingCount} existing)`,
        data: result,
      });
    }

    const result = await createIntakeGroupsBulk({
      campusId: req.body.campusId || req.body.campus_id,
      items: req.body.items,
    });
    return res.status(201).json({
      success: true,
      message: `Promotions ready: ${result.promotionCount} program year(s), ${result.createdCount} group(s) created, ${result.existingCount} already existed.`,
      data: result,
    });
  } catch (error) {
    const status = error.code === "VALIDATION" || error.code === "NOT_FOUND" ? 400 : 500;
    return res.status(status).json({ success: false, message: error.message });
  }
};
