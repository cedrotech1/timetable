const express = require("express");
const {
  getAllProgramsController,
  getProgramByIdController,
  createProgramController,
  updateProgramController,
  deleteProgramController,
} = require("../controllers/programController.js");
const { protect } = require("../middlewares/protect.js");
const { requireManageAccess } = require("../middlewares/roleAccess.js");

const router = express.Router();

router.get("/", getAllProgramsController);
router.get("/:id", getProgramByIdController);
router.post("/", protect, requireManageAccess, createProgramController);
router.put("/:id", protect, requireManageAccess, updateProgramController);
router.delete("/:id", protect, requireManageAccess, deleteProgramController);

module.exports = router;
