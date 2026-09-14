const express = require("express");
const {
  getAllCampusesController,
  getCampusByIdController,
  createCampusController,
  updateCampusController,
  deleteCampusController,
} = require("../controllers/campusController.js");
const { protect } = require("../middlewares/protect.js");
const { requireManageAccess } = require("../middlewares/roleAccess.js");

const router = express.Router();

router.get("/", getAllCampusesController);
router.get("/:id", getCampusByIdController);
router.post("/", protect, requireManageAccess, createCampusController);
router.put("/:id", protect, requireManageAccess, updateCampusController);
router.delete("/:id", protect, requireManageAccess, deleteCampusController);

module.exports = router;
