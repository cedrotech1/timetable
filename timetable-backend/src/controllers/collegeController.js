import {
  getAllColleges,
  getCollegeById,
  createCollege,
  updateCollege,
  deleteCollege,
} from "../services/collegeService.js";
import { logSuccess, logFailure } from "../services/logService.js";

export const getAllCollegesController = async (req, res) => {
  try {
    const data = await getAllColleges();
    return res.status(200).json({ success: true, message: "Colleges retrieved successfully", data });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const getCollegeByIdController = async (req, res) => {
  try {
    const college = await getCollegeById(req.params.id);
    if (!college) {
      return res.status(404).json({ success: false, message: "College not found" });
    }
    return res.status(200).json({ success: true, message: "College retrieved successfully", data: college });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const createCollegeController = async (req, res) => {
  try {
    const { name, fullName } = req.body;
    if (!name || !fullName) {
      return res.status(400).json({ success: false, message: "Please provide name and fullName" });
    }
    const college = await createCollege({ name, fullName });
    logSuccess(req, `Created college: ${name}`, "College", "CREATE", null, college.id, "College");
    return res.status(201).json({ success: true, message: "College created successfully", data: college });
  } catch (error) {
    logFailure(req, "Failed to create college", "College", "CREATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const updateCollegeController = async (req, res) => {
  try {
    const { name, fullName } = req.body;
    const college = await updateCollege(req.params.id, { name, fullName });
    if (!college) {
      return res.status(404).json({ success: false, message: "College not found" });
    }
    logSuccess(req, `Updated college: ${college.name}`, "College", "UPDATE", null, college.id, "College");
    return res.status(200).json({ success: true, message: "College updated successfully", data: college });
  } catch (error) {
    logFailure(req, "Failed to update college", "College", "UPDATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const deleteCollegeController = async (req, res) => {
  try {
    const college = await deleteCollege(req.params.id);
    if (!college) {
      return res.status(404).json({ success: false, message: "College not found" });
    }
    logSuccess(req, `Deleted college: ${college.name}`, "College", "DELETE", null, college.id, "College");
    return res.status(200).json({ success: true, message: "College deleted successfully" });
  } catch (error) {
    logFailure(req, "Failed to delete college", "College", "DELETE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};
