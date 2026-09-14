const express = require("express");
const {
  getAllUsersController,
  getUserByIdController,
  createUserController,
  updateUserController,
  deleteUserController,
  activateUserController,
  deactivateUserController,
} = require("../controllers/authController.js");
const { protect } = require("../middlewares/protect.js");
const { checkRoleModificationAccess, requireAdmin } = require("../middlewares/roleAccess.js");

const router = express.Router();

router.get("/", protect, requireAdmin, getAllUsersController);
router.get("/:id", protect, requireAdmin, getUserByIdController);
router.post("/", protect, checkRoleModificationAccess, createUserController);
router.put("/:id", protect, checkRoleModificationAccess, updateUserController);
router.delete("/:id", protect, requireAdmin, deleteUserController);
router.put("/:id/activate", protect, requireAdmin, activateUserController);
router.put("/:id/deactivate", protect, requireAdmin, deactivateUserController);

module.exports = router;
