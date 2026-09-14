import {
  getAllPrograms,
  getProgramById,
  createProgram,
  updateProgram,
  deleteProgram,
} from "../services/programService.js";
import { logSuccess, logFailure } from "../services/logService.js";

export const getAllProgramsController = async (req, res) => {
  try {
    const data = await getAllPrograms({ schoolId: req.query.schoolId });
    return res.status(200).json({ success: true, message: "Programs retrieved successfully", data });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const getProgramByIdController = async (req, res) => {
  try {
    const program = await getProgramById(req.params.id);
    if (!program) {
      return res.status(404).json({ success: false, message: "Program not found" });
    }
    return res.status(200).json({ success: true, message: "Program retrieved successfully", data: program });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const createProgramController = async (req, res) => {
  try {
    const { name, code, schoolId } = req.body;
    if (!name || !schoolId) {
      return res.status(400).json({ success: false, message: "Please provide name and schoolId" });
    }
    const program = await createProgram({ name, code: code || null, schoolId });
    logSuccess(req, `Created program: ${name}`, "Program", "CREATE", null, program.id, "Program");
    return res.status(201).json({ success: true, message: "Program created successfully", data: program });
  } catch (error) {
    logFailure(req, "Failed to create program", "Program", "CREATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const updateProgramController = async (req, res) => {
  try {
    const { name, code, schoolId } = req.body;
    const program = await updateProgram(req.params.id, { name, code, schoolId });
    if (!program) {
      return res.status(404).json({ success: false, message: "Program not found" });
    }
    logSuccess(req, `Updated program: ${program.name}`, "Program", "UPDATE", null, program.id, "Program");
    return res.status(200).json({ success: true, message: "Program updated successfully", data: program });
  } catch (error) {
    logFailure(req, "Failed to update program", "Program", "UPDATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const deleteProgramController = async (req, res) => {
  try {
    const program = await deleteProgram(req.params.id);
    if (!program) {
      return res.status(404).json({ success: false, message: "Program not found" });
    }
    logSuccess(req, `Deleted program: ${program.name}`, "Program", "DELETE", null, program.id, "Program");
    return res.status(200).json({ success: true, message: "Program deleted successfully" });
  } catch (error) {
    logFailure(req, "Failed to delete program", "Program", "DELETE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};
