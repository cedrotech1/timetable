import bcrypt from "bcryptjs";
import { Op } from "sequelize";
import db from "../database/models/index.js";

const { Users } = db;

const userIncludes = [
  { association: "campus", attributes: ["id", "name"] },
  { association: "college", attributes: ["id", "name", "fullName"] },
  { association: "school", attributes: ["id", "name"] },
];

const publicAttrs = { exclude: ["password", "resetcode"] };

export const getUsers = async (filters = {}) => {
  const where = { deleted: { [Op.ne]: "yes" } };
  if (filters.role) where.role = filters.role;
  if (filters.campusId) where.campusId = filters.campusId;
  if (filters.collegeId) where.collegeId = filters.collegeId;
  if (filters.schoolId) where.schoolId = filters.schoolId;

  return Users.findAll({
    where,
    attributes: publicAttrs,
    include: userIncludes,
    order: [["names", "ASC"]],
  });
};

export const getUserById = async (id) => {
  return Users.findByPk(id, {
    attributes: publicAttrs,
    include: userIncludes,
  });
};

export const getUserByLogin = async (login) => {
  const normalized = String(login || "").trim();
  if (!normalized) return null;

  return Users.findOne({
    where: {
      deleted: { [Op.ne]: "yes" },
      [Op.or]: [
        { urEmail: { [Op.iLike]: normalized } },
        { email: { [Op.iLike]: normalized } },
      ],
    },
    order: [["id", "DESC"]],
  });
};

/** @deprecated use getUserByLogin — kept for auth compatibility */
export const getUserByEmail = getUserByLogin;

export const createUser = async (userData) => {
  const payload = { ...userData };
  const salt = await bcrypt.genSalt(10);
  payload.password = await bcrypt.hash(payload.password, salt);
  if (payload.active === undefined) payload.active = true;
  if (!payload.deleted) payload.deleted = "no";
  return Users.create(payload);
};

export const updateUser = async (id, userData) => {
  const user = await Users.findByPk(id);
  if (!user) return null;

  const payload = { ...userData };
  if (payload.password) {
    const salt = await bcrypt.genSalt(10);
    payload.password = await bcrypt.hash(payload.password, salt);
  }

  await user.update(payload);
  return getUserById(id);
};

export const updateUserPassword = async (id, plainPassword) => {
  const salt = await bcrypt.genSalt(10);
  const password = await bcrypt.hash(plainPassword, salt);
  await Users.update({ password }, { where: { id } });
};

export const softDeleteUser = async (id) => {
  const user = await Users.findByPk(id);
  if (!user) return null;
  await user.update({ deleted: "yes", active: false });
  return user;
};

export const setUserActive = async (id, active) => {
  const user = await Users.findByPk(id);
  if (!user) return null;
  await user.update({ active: !!active });
  return getUserById(id);
};
