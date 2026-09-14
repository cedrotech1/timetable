import db from "../database/models/index.js";

const { College } = db;

export const getAllColleges = async () => {
  return College.findAll({ order: [["name", "ASC"]] });
};

export const getCollegeById = async (id) => {
  return College.findByPk(id, {
    include: [{ association: "schools" }],
  });
};

export const createCollege = async (data) => {
  return College.create(data);
};

export const updateCollege = async (id, data) => {
  const college = await College.findByPk(id);
  if (!college) return null;
  await college.update(data);
  return college;
};

export const deleteCollege = async (id) => {
  const college = await College.findByPk(id);
  if (!college) return null;
  await college.destroy();
  return college;
};
