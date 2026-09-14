const express = require("express");

const authRouter = require("./authRouter.js");
const userRouter = require("./userRouter.js");
const campusRouter = require("./campusRouter.js");
const collegeRouter = require("./collegeRouter.js");
const schoolRouter = require("./schoolRouter.js");
const programRouter = require("./programRouter.js");
const moduleRouter = require("./moduleRouter.js");
const facilityRouter = require("./facilityRouter.js");
const organizationRouter = require("./organizationRouter.js");
const timetableRouter = require("./timetableRouter.js");

const router = express.Router();

router.get("/health", (req, res) => {
  res.status(200).json({
    success: true,
    message: "UR Timetable API is running",
    timestamp: new Date().toISOString(),
  });
});

router.use("/auth", authRouter);
router.use("/users", userRouter);
router.use("/campuses", campusRouter);
router.use("/colleges", collegeRouter);
router.use("/schools", schoolRouter);
router.use("/programs", programRouter);
router.use("/modules", moduleRouter);
router.use("/facilities", facilityRouter);
router.use("/organization", organizationRouter);
router.use("/timetables", timetableRouter);

module.exports = router;
