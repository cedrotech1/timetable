const express = require("express");
const {
  getAllFacilitiesController,
  getFacilityByIdController,
  createFacilityController,
  updateFacilityController,
  deleteFacilityController,
} = require("../controllers/facilityController.js");
const { protect } = require("../middlewares/protect.js");
const { requireManageAccess } = require("../middlewares/roleAccess.js");

const router = express.Router();

router.get("/", getAllFacilitiesController);
router.get("/:id", getFacilityByIdController);
router.post("/", protect, requireManageAccess, createFacilityController);
router.put("/:id", protect, requireManageAccess, updateFacilityController);
router.delete("/:id", protect, requireManageAccess, deleteFacilityController);

module.exports = router;
