import db from "../database/models/index.js";
import { Op } from "sequelize";

const { Module, Timetable, TimetableSession, TimetableGroup, TimetableLecturer, sequelize } = db;

export const getAllModules = async (filters = {}) => {
  const where = {};
  if (filters.programId) where.programId = filters.programId;
  if (filters.year) where.year = filters.year;
  if (filters.semester) where.semester = filters.semester;

  return Module.findAll({
    where,
    include: [
      {
        association: "program",
        attributes: ["id", "name", "code", "schoolId"],
      },
    ],
    order: [
      ["year", "ASC"],
      ["semester", "ASC"],
      ["name", "ASC"],
    ],
  });
};

export const getModuleById = async (id) => {
  return Module.findByPk(id, {
    include: [{ association: "program" }],
  });
};

export const createModule = async (data) => {
  return Module.create(data);
};

export const updateModule = async (id, data) => {
  const moduleRow = await Module.findByPk(id);
  if (!moduleRow) return null;
  await moduleRow.update(data);
  return moduleRow;
};

export const deleteModule = async (id) => {
  const moduleRow = await Module.findByPk(id);
  if (!moduleRow) return null;
  await moduleRow.destroy();
  return moduleRow;
};

/**
 * Wipe all modules (and teaching plans that reference them).
 * Excel upload / rematch can recreate modules from the spreadsheet again.
 */
export async function truncateAllModules() {
  return sequelize.transaction(async (t) => {
    const beforeModules = await Module.count({ transaction: t });
    const moduleIds = (await Module.findAll({ attributes: ["id"], transaction: t })).map((m) => m.id);

    let clearedTimetables = 0;
    if (moduleIds.length) {
      const plans = await Timetable.findAll({
        attributes: ["id"],
        where: { moduleId: { [Op.in]: moduleIds } },
        transaction: t,
      });
      const ttIds = plans.map((p) => p.id);
      clearedTimetables = ttIds.length;
      if (ttIds.length) {
        await TimetableSession.destroy({ where: { timetableId: { [Op.in]: ttIds } }, transaction: t });
        await TimetableLecturer.destroy({ where: { timetableId: { [Op.in]: ttIds } }, transaction: t });
        await TimetableGroup.destroy({ where: { timetableId: { [Op.in]: ttIds } }, transaction: t });
        await Timetable.destroy({ where: { id: { [Op.in]: ttIds } }, transaction: t });
      }
    }

    await Module.destroy({ where: {}, transaction: t });

    try {
      await sequelize.query(`ALTER SEQUENCE IF EXISTS modules_id_seq RESTART WITH 1`, { transaction: t });
    } catch {
      /* ignore */
    }

    return {
      deletedModules: beforeModules,
      clearedTimetables,
      message: `Truncated ${beforeModules} module(s)${
        clearedTimetables ? ` and ${clearedTimetables} linked teaching plan(s)` : ""
      }. Re-upload Excel or rematch to recreate modules from the spreadsheet.`,
    };
  });
}
