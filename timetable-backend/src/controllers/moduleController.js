import {
  getAllModules,
  getModuleById,
  createModule,
  updateModule,
  deleteModule,
} from "../services/moduleService.js";
import { logSuccess, logFailure } from "../services/logService.js";

export const getAllModulesController = async (req, res) => {
  try {
    const data = await getAllModules({
      programId: req.query.programId,
      year: req.query.year,
      semester: req.query.semester,
    });
    return res.status(200).json({ success: true, message: "Modules retrieved successfully", data });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const getModuleByIdController = async (req, res) => {
  try {
    const moduleRow = await getModuleById(req.params.id);
    if (!moduleRow) {
      return res.status(404).json({ success: false, message: "Module not found" });
    }
    return res.status(200).json({ success: true, message: "Module retrieved successfully", data: moduleRow });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const createModuleController = async (req, res) => {
  try {
    const { name, code, credits, year, semester, programId } = req.body;
    if (!name || year === undefined || !semester || !programId) {
      return res.status(400).json({
        success: false,
        message: "Please provide name, year, semester, and programId",
      });
    }
    const moduleRow = await createModule({
      name,
      code: code || null,
      credits: credits ?? 0,
      year,
      semester: String(semester),
      programId,
    });
    logSuccess(req, `Created module: ${name}`, "Module", "CREATE", null, moduleRow.id, "Module");
    return res.status(201).json({ success: true, message: "Module created successfully", data: moduleRow });
  } catch (error) {
    logFailure(req, "Failed to create module", "Module", "CREATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const updateModuleController = async (req, res) => {
  try {
    const { name, code, credits, year, semester, programId } = req.body;
    const moduleRow = await updateModule(req.params.id, {
      name,
      code,
      credits,
      year,
      semester: semester !== undefined ? String(semester) : undefined,
      programId,
    });
    if (!moduleRow) {
      return res.status(404).json({ success: false, message: "Module not found" });
    }
    logSuccess(req, `Updated module: ${moduleRow.name}`, "Module", "UPDATE", null, moduleRow.id, "Module");
    return res.status(200).json({ success: true, message: "Module updated successfully", data: moduleRow });
  } catch (error) {
    logFailure(req, "Failed to update module", "Module", "UPDATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const deleteModuleController = async (req, res) => {
  try {
    const moduleRow = await deleteModule(req.params.id);
    if (!moduleRow) {
      return res.status(404).json({ success: false, message: "Module not found" });
    }
    logSuccess(req, `Deleted module: ${moduleRow.name}`, "Module", "DELETE", null, moduleRow.id, "Module");
    return res.status(200).json({ success: true, message: "Module deleted successfully" });
  } catch (error) {
    logFailure(req, "Failed to delete module", "Module", "DELETE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};
