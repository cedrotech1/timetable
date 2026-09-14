import db from "../database/models/index.js";

const { Program } = db;

export const getAllPrograms = async (filters = {}) => {
  const where = {};
  if (filters.schoolId) where.schoolId = filters.schoolId;

  return Program.findAll({
    where,
    include: [
      {
        association: "school",
        attributes: ["id", "name", "collegeId"],
        include: [{ association: "college", attributes: ["id", "name", "fullName"] }],
      },
    ],
    order: [["name", "ASC"]],
  });
};

export const getProgramById = async (id) => {
  return Program.findByPk(id, {
    include: [
      {
        association: "school",
        include: [{ association: "college", attributes: ["id", "name", "fullName"] }],
      },
      { association: "modules" },
    ],
  });
};

export const createProgram = async (data) => {
  return Program.create(data);
};

export const updateProgram = async (id, data) => {
  const program = await Program.findByPk(id);
  if (!program) return null;
  await program.update(data);
  return program;
};

export const deleteProgram = async (id) => {
  const program = await Program.findByPk(id);
  if (!program) return null;
  await program.destroy();
  return program;
};
