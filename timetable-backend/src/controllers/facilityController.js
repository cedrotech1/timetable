import {
  getAllFacilities,
  getFacilityById,
  createFacility,
  updateFacility,
  deleteFacility,
} from "../services/facilityService.js";
import { logSuccess, logFailure } from "../services/logService.js";

export const getAllFacilitiesController = async (req, res) => {
  try {
    const data = await getAllFacilities({
      campusId: req.query.campusId,
      type: req.query.type,
    });
    return res.status(200).json({ success: true, message: "Facilities retrieved successfully", data });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const getFacilityByIdController = async (req, res) => {
  try {
    const facility = await getFacilityById(req.params.id);
    if (!facility) {
      return res.status(404).json({ success: false, message: "Facility not found" });
    }
    return res.status(200).json({ success: true, message: "Facility retrieved successfully", data: facility });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const createFacilityController = async (req, res) => {
  try {
    const { name, name2, type, capacity, campusId, site, buildName, buildCode } = req.body;
    if (!name || !campusId) {
      return res.status(400).json({ success: false, message: "Please provide name and campusId" });
    }
    const facility = await createFacility({
      name,
      name2: name2 || name,
      type: type || null,
      capacity: capacity ?? null,
      campusId,
      site: site || null,
      buildName: buildName || null,
      buildCode: buildCode || null,
    });
    logSuccess(req, `Created facility: ${name}`, "Facility", "CREATE", null, facility.id, "Facility");
    return res.status(201).json({ success: true, message: "Facility created successfully", data: facility });
  } catch (error) {
    logFailure(req, "Failed to create facility", "Facility", "CREATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const updateFacilityController = async (req, res) => {
  try {
    const { name, name2, type, capacity, campusId, site, buildName, buildCode } = req.body;
    const facility = await updateFacility(req.params.id, {
      name,
      name2,
      type,
      capacity,
      campusId,
      site,
      buildName,
      buildCode,
    });
    if (!facility) {
      return res.status(404).json({ success: false, message: "Facility not found" });
    }
    logSuccess(req, `Updated facility: ${facility.name}`, "Facility", "UPDATE", null, facility.id, "Facility");
    return res.status(200).json({ success: true, message: "Facility updated successfully", data: facility });
  } catch (error) {
    logFailure(req, "Failed to update facility", "Facility", "UPDATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const deleteFacilityController = async (req, res) => {
  try {
    const facility = await deleteFacility(req.params.id);
    if (!facility) {
      return res.status(404).json({ success: false, message: "Facility not found" });
    }
    logSuccess(req, `Deleted facility: ${facility.name}`, "Facility", "DELETE", null, facility.id, "Facility");
    return res.status(200).json({ success: true, message: "Facility deleted successfully" });
  } catch (error) {
    logFailure(req, "Failed to delete facility", "Facility", "DELETE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};
