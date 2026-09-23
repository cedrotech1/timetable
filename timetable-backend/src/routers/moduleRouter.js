const express = require("express");
const {
  getAllModulesController,
  getModuleByIdController,
  createModuleController,
  updateModuleController,
  deleteModuleController,
  truncateModulesController,
} = require("../controllers/moduleController.js");
const { protect } = require("../middlewares/protect.js");
const { requireManageAccess, requireAdmin } = require("../middlewares/roleAccess.js");

const router = express.Router();

router.get("/", getAllModulesController);
router.post("/truncate", protect, requireAdmin, truncateModulesController);
router.get("/:id", getModuleByIdController);
router.post("/", protect, requireManageAccess, createModuleController);
router.put("/:id", protect, requireManageAccess, updateModuleController);
router.delete("/:id", protect, requireManageAccess, deleteModuleController);

module.exports = router;
