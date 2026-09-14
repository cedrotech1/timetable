import {
  getAllCampuses,
  getCampusById,
  createCampus,
  updateCampus,
  deleteCampus,
} from "../services/campusService.js";
import { logSuccess, logFailure } from "../services/logService.js";

export const getAllCampusesController = async (req, res) => {
  try {
    const data = await getAllCampuses();
    return res.status(200).json({ success: true, message: "Campuses retrieved successfully", data });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const getCampusByIdController = async (req, res) => {
  try {
    const campus = await getCampusById(req.params.id);
    if (!campus) {
      return res.status(404).json({ success: false, message: "Campus not found" });
    }
    return res.status(200).json({ success: true, message: "Campus retrieved successfully", data: campus });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const createCampusController = async (req, res) => {
  try {
    const { name } = req.body;
    if (!name) {
      return res.status(400).json({ success: false, message: "Please provide campus name" });
    }
    const campus = await createCampus({ name });
    logSuccess(req, `Created campus: ${name}`, "Campus", "CREATE", null, campus.id, "Campus");
    return res.status(201).json({ success: true, message: "Campus created successfully", data: campus });
  } catch (error) {
    logFailure(req, "Failed to create campus", "Campus", "CREATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const updateCampusController = async (req, res) => {
  try {
    const campus = await updateCampus(req.params.id, { name: req.body.name });
    if (!campus) {
      return res.status(404).json({ success: false, message: "Campus not found" });
    }
    logSuccess(req, `Updated campus: ${campus.name}`, "Campus", "UPDATE", null, campus.id, "Campus");
    return res.status(200).json({ success: true, message: "Campus updated successfully", data: campus });
  } catch (error) {
    logFailure(req, "Failed to update campus", "Campus", "UPDATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const deleteCampusController = async (req, res) => {
  try {
    const campus = await deleteCampus(req.params.id);
    if (!campus) {
      return res.status(404).json({ success: false, message: "Campus not found" });
    }
    logSuccess(req, `Deleted campus: ${campus.name}`, "Campus", "DELETE", null, campus.id, "Campus");
    return res.status(200).json({ success: true, message: "Campus deleted successfully" });
  } catch (error) {
    logFailure(req, "Failed to delete campus", "Campus", "DELETE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};
