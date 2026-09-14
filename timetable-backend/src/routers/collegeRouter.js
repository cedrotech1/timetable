const express = require("express");
const {
  getAllCollegesController,
  getCollegeByIdController,
  createCollegeController,
  updateCollegeController,
  deleteCollegeController,
} = require("../controllers/collegeController.js");
const { protect } = require("../middlewares/protect.js");
const { requireManageAccess } = require("../middlewares/roleAccess.js");

const router = express.Router();

router.get("/", getAllCollegesController);
router.get("/:id", getCollegeByIdController);
router.post("/", protect, requireManageAccess, createCollegeController);
router.put("/:id", protect, requireManageAccess, updateCollegeController);
router.delete("/:id", protect, requireManageAccess, deleteCollegeController);

module.exports = router;
