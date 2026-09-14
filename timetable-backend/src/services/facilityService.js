import db from "../database/models/index.js";

const { Facility } = db;

export const getAllFacilities = async (filters = {}) => {
  const where = {};
  if (filters.campusId) where.campusId = filters.campusId;
  if (filters.type) where.type = filters.type;

  return Facility.findAll({
    where,
    include: [{ association: "campus", attributes: ["id", "name"] }],
    order: [["name", "ASC"]],
  });
};

export const getFacilityById = async (id) => {
  return Facility.findByPk(id, {
    include: [{ association: "campus", attributes: ["id", "name"] }],
  });
};

export const createFacility = async (data) => {
  return Facility.create(data);
};

export const updateFacility = async (id, data) => {
  const facility = await Facility.findByPk(id);
  if (!facility) return null;
  await facility.update(data);
  return facility;
};

export const deleteFacility = async (id) => {
  const facility = await Facility.findByPk(id);
  if (!facility) return null;
  await facility.destroy();
  return facility;
};
