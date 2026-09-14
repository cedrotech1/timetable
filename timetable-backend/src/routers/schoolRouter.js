const express = require("express");
const {
  getAllSchoolsController,
  getSchoolByIdController,
  createSchoolController,
  updateSchoolController,
  deleteSchoolController,
} = require("../controllers/schoolController.js");
const { protect } = require("../middlewares/protect.js");
const { requireManageAccess } = require("../middlewares/roleAccess.js");

const router = express.Router();

router.get("/", getAllSchoolsController);
router.get("/:id", getSchoolByIdController);
router.post("/", protect, requireManageAccess, createSchoolController);
router.put("/:id", protect, requireManageAccess, updateSchoolController);
router.delete("/:id", protect, requireManageAccess, deleteSchoolController);

module.exports = router;
