import db from "../database/models/index.js";

const { School } = db;

export const getAllSchools = async (filters = {}) => {
  const where = {};
  if (filters.collegeId) where.collegeId = filters.collegeId;

  return School.findAll({
    where,
    include: [{ association: "college", attributes: ["id", "name", "fullName"] }],
    order: [["name", "ASC"]],
  });
};

export const getSchoolById = async (id) => {
  return School.findByPk(id, {
    include: [
      { association: "college", attributes: ["id", "name", "fullName"] },
      { association: "programs" },
    ],
  });
};

export const createSchool = async (data) => {
  return School.create(data);
};

export const updateSchool = async (id, data) => {
  const school = await School.findByPk(id);
  if (!school) return null;
  await school.update(data);
  return school;
};

export const deleteSchool = async (id) => {
  const school = await School.findByPk(id);
  if (!school) return null;
  await school.destroy();
  return school;
};
