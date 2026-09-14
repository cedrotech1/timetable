import {
  getAllSchools,
  getSchoolById,
  createSchool,
  updateSchool,
  deleteSchool,
} from "../services/schoolService.js";
import { logSuccess, logFailure } from "../services/logService.js";

export const getAllSchoolsController = async (req, res) => {
  try {
    const data = await getAllSchools({ collegeId: req.query.collegeId });
    return res.status(200).json({ success: true, message: "Schools retrieved successfully", data });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const getSchoolByIdController = async (req, res) => {
  try {
    const school = await getSchoolById(req.params.id);
    if (!school) {
      return res.status(404).json({ success: false, message: "School not found" });
    }
    return res.status(200).json({ success: true, message: "School retrieved successfully", data: school });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const createSchoolController = async (req, res) => {
  try {
    const { name, collegeId } = req.body;
    if (!name || !collegeId) {
      return res.status(400).json({ success: false, message: "Please provide name and collegeId" });
    }
    const school = await createSchool({ name, collegeId });
    logSuccess(req, `Created school: ${name}`, "School", "CREATE", null, school.id, "School");
    return res.status(201).json({ success: true, message: "School created successfully", data: school });
  } catch (error) {
    logFailure(req, "Failed to create school", "School", "CREATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const updateSchoolController = async (req, res) => {
  try {
    const { name, collegeId } = req.body;
    const school = await updateSchool(req.params.id, { name, collegeId });
    if (!school) {
      return res.status(404).json({ success: false, message: "School not found" });
    }
    logSuccess(req, `Updated school: ${school.name}`, "School", "UPDATE", null, school.id, "School");
    return res.status(200).json({ success: true, message: "School updated successfully", data: school });
  } catch (error) {
    logFailure(req, "Failed to update school", "School", "UPDATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const deleteSchoolController = async (req, res) => {
  try {
    const school = await deleteSchool(req.params.id);
    if (!school) {
      return res.status(404).json({ success: false, message: "School not found" });
    }
    logSuccess(req, `Deleted school: ${school.name}`, "School", "DELETE", null, school.id, "School");
    return res.status(200).json({ success: true, message: "School deleted successfully" });
  } catch (error) {
    logFailure(req, "Failed to delete school", "School", "DELETE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};
