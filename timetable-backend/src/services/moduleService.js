import db from "../database/models/index.js";

const { Module } = db;

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
