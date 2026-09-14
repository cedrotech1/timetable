const express = require("express");
const {
  getSettingsController,
  updateSettingsController,
  createAcademicYearController,
  deleteAcademicYearController,
  resetTimetablesController,
  clearIntakesGroupsController,
  listTimetablesController,
  getTimetableController,
  listPublicTimetablesController,
  createTimetableController,
  updateTimetableController,
  deleteTimetableController,
  bulkTimetableController,
  availableFacilitiesController,
  listIntakesController,
  createIntakeController,
  listGroupsController,
  listLecturersController,
  matchImportController,
  createIntakesBulkController,
  autoAssignFacilitiesController,
  facilityCalendarController,
} = require("../controllers/timetableController.js");
const { protect } = require("../middlewares/protect.js");
const { requireManageAccess, requireAdmin } = require("../middlewares/roleAccess.js");

const router = express.Router();

router.get("/public", listPublicTimetablesController);

router.get("/settings", protect, getSettingsController);
router.put("/settings", protect, requireManageAccess, updateSettingsController);
router.post("/settings/academic-years", protect, requireAdmin, createAcademicYearController);
router.delete("/settings/academic-years/:id", protect, requireAdmin, deleteAcademicYearController);
router.post("/settings/reset-timetables", protect, requireAdmin, resetTimetablesController);
router.post("/settings/clear-intakes-groups", protect, requireAdmin, clearIntakesGroupsController);

router.get("/", protect, listTimetablesController);
router.post("/", protect, requireManageAccess, createTimetableController);
router.put("/:id", protect, requireManageAccess, updateTimetableController);
router.delete("/:id", protect, requireManageAccess, deleteTimetableController);
router.post("/bulk", protect, requireManageAccess, bulkTimetableController);
router.post("/facilities/available", protect, availableFacilitiesController);
router.get("/facilities/calendar", protect, facilityCalendarController);
router.get("/facilities/:id/calendar", protect, facilityCalendarController);
router.get("/lecturers", protect, listLecturersController);

router.post("/import/match", protect, requireManageAccess, matchImportController);
router.post("/import/auto-facilities", protect, requireManageAccess, autoAssignFacilitiesController);
router.post("/import/create-intakes", protect, requireManageAccess, createIntakesBulkController);

router.get("/intakes", protect, listIntakesController);
router.post("/intakes", protect, requireManageAccess, createIntakeController);
router.get("/groups", protect, listGroupsController);

router.get("/:id", protect, getTimetableController);

module.exports = router;
