const express = require("express");
const { login, changePassword, getMe } = require("../controllers/authController.js");
const { protect } = require("../middlewares/protect.js");

const router = express.Router();

router.post("/login", login);
router.get("/me", protect, getMe);
router.put("/change-password", protect, changePassword);

module.exports = router;
