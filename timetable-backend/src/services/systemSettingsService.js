import db from "../database/models/index.js";
import { Op } from "sequelize";

const {
  sequelize,
  Timetable,
  TimetableSession,
  TimetableGroup,
  TimetableLecturer,
  StudentGroup,
  Intake,
  AcademicYear,
  TimetableSettings,
} = db;

export async function getSystemCounts() {
  const [timetables, sessions, intakes, groups] = await Promise.all([
    Timetable.count(),
    TimetableSession.count(),
    Intake.count(),
    StudentGroup.count(),
  ]);
  return { timetables, sessions, intakes, groups };
}

export async function updateSystemSettings({ status, academicYearId, semester }, user) {
  const yearId = Number(academicYearId);
  const year = await AcademicYear.findByPk(yearId);
  if (!year) {
    const err = new Error("Selected academic year does not exist");
    err.code = "NOT_FOUND";
    throw err;
  }

  let settings = await TimetableSettings.findOne({ order: [["id", "ASC"]] });
  const payload = {
    status: status || "live",
    academicYearId: yearId,
    semester: String(semester || "1"),
    updatedBy: user?.id || null,
  };

  if (settings) {
    await settings.update(payload);
  } else {
    settings = await TimetableSettings.create(payload);
  }

  return TimetableSettings.findByPk(settings.id, {
    include: [{ model: AcademicYear, as: "academicYear" }],
  });
}

export async function createAcademicYear(yearLabel) {
  const label = String(yearLabel || "").trim();
  if (!label) {
    const err = new Error("Academic year label is required");
    err.code = "VALIDATION";
    throw err;
  }
  const existing = await AcademicYear.findOne({ where: { yearLabel: label } });
  if (existing) {
    const err = new Error("Academic year already exists");
    err.code = "VALIDATION";
    throw err;
  }
  return AcademicYear.create({ yearLabel: label });
}

export async function deleteAcademicYear(yearId) {
  const id = Number(yearId);
  const inUse = await TimetableSettings.findOne({ where: { academicYearId: id } });
  if (inUse) {
    const err = new Error("Cannot delete academic year as it is currently in use");
    err.code = "VALIDATION";
    throw err;
  }
  const year = await AcademicYear.findByPk(id);
  if (!year) {
    const err = new Error("Academic year not found");
    err.code = "NOT_FOUND";
    throw err;
  }
  await year.destroy();
  return true;
}

/**
 * PHP system.php reset_timetables — wipe all teaching plans + sessions/groups/lecturers.
 */
export async function resetAllTimetables() {
  return sequelize.transaction(async (t) => {
    const before = await Timetable.count({ transaction: t });
    await TimetableSession.destroy({ where: {}, transaction: t });
    await TimetableLecturer.destroy({ where: {}, transaction: t });
    await TimetableGroup.destroy({ where: {}, transaction: t });
    await Timetable.destroy({ where: {}, transaction: t });
    return {
      deletedTimetables: before,
      message: "All timetable data has been reset.",
    };
  });
}

/**
 * PHP system.php reset_intakes_groups — clear promotions + groups (+ linked plans).
 */
export async function clearIntakesAndGroups() {
  return sequelize.transaction(async (t) => {
    const intakeCount = await Intake.count({ transaction: t });
    const groupCount = await StudentGroup.count({ transaction: t });

    const linked = await TimetableGroup.findAll({
      attributes: ["timetableId"],
      group: ["timetableId"],
      transaction: t,
    });
    const ttIds = [...new Set(linked.map((r) => Number(r.timetableId)).filter(Boolean))];

    if (ttIds.length) {
      await TimetableSession.destroy({ where: { timetableId: { [Op.in]: ttIds } }, transaction: t });
      await TimetableLecturer.destroy({ where: { timetableId: { [Op.in]: ttIds } }, transaction: t });
      await TimetableGroup.destroy({ where: {}, transaction: t });
      await Timetable.destroy({ where: { id: { [Op.in]: ttIds } }, transaction: t });
    } else {
      await TimetableGroup.destroy({ where: {}, transaction: t });
    }

    await StudentGroup.destroy({ where: {}, transaction: t });
    await Intake.destroy({ where: {}, transaction: t });

    // Reset identity sequences (Postgres) so recreate starts clean
    try {
      await sequelize.query(`ALTER SEQUENCE IF EXISTS student_groups_id_seq RESTART WITH 1`, { transaction: t });
      await sequelize.query(`ALTER SEQUENCE IF EXISTS intakes_id_seq RESTART WITH 1`, { transaction: t });
    } catch {
      // ignore if not Postgres / sequence names differ
    }

    return {
      clearedIntakes: intakeCount,
      clearedGroups: groupCount,
      clearedTimetables: ttIds.length,
      message: `Cleared ${intakeCount} promotion/intake(s), ${groupCount} group(s)${
        ttIds.length ? `, and ${ttIds.length} linked timetable(s)` : ""
      }. You can recreate them from Excel Import.`,
    };
  });
}
