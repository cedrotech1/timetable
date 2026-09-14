import db from "../database/models/index.js";

const { Campus } = db;

export const getAllCampuses = async () => {
  return Campus.findAll({ order: [["name", "ASC"]] });
};

export const getCampusById = async (id) => {
  return Campus.findByPk(id);
};

export const createCampus = async (data) => {
  return Campus.create(data);
};

export const updateCampus = async (id, data) => {
  const campus = await Campus.findByPk(id);
  if (!campus) return null;
  await campus.update(data);
  return campus;
};

export const deleteCampus = async (id) => {
  const campus = await Campus.findByPk(id);
  if (!campus) return null;
  await campus.destroy();
  return campus;
};
