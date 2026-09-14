import jwt from "jsonwebtoken";
import {
  getUserByLogin,
  createUser,
  getUsers,
  getUserById,
  updateUser,
  softDeleteUser,
  setUserActive,
  updateUserPassword,
} from "../services/userService.js";
import { logSuccess, logFailure, logUserActivity } from "../services/logService.js";
import { verifyLoginPassword } from "../utils/staffPassword.js";
import { VALID_USER_ROLES } from "../utils/roleHelpers.js";

function generateToken(id) {
  return jwt.sign({ id }, process.env.JWT_SECRET, {
    expiresIn: process.env.JWT_EXPIRES_IN || "7d",
  });
}

function toPublicUser(user) {
  const json = typeof user.toJSON === "function" ? user.toJSON() : { ...user };
  delete json.password;
  delete json.resetcode;
  return json;
}

export const login = async (req, res) => {
  const email = req.body.email || req.body.urEmail;
  const { password } = req.body;

  if (!email) {
    return res.status(400).json({ success: false, message: "Please provide email" });
  }
  if (!password) {
    return res.status(400).json({ success: false, message: "Please provide password" });
  }

  const user = await getUserByLogin(email);
  if (!user || String(user.deleted || "").toLowerCase() === "yes") {
    logUserActivity(req, "Login failed - user not found", "Authentication", "LOGIN", `Email: ${email}`);
    return res.status(400).json({ success: false, message: "Invalid email or password" });
  }

  const authResult = await verifyLoginPassword(password, user.password);
  if (!authResult.ok) {
    logUserActivity(req, "Login failed - invalid password", "Authentication", "LOGIN", `Email: ${email}`);
    return res.status(400).json({ success: false, message: "Invalid email or password" });
  }

  if (!user.active) {
    return res.status(400).json({ success: false, message: "Your account is not active" });
  }

  logUserActivity(
    req,
    `User ${user.names} logged in successfully`,
    "Authentication",
    "LOGIN",
    `Email: ${user.urEmail}, Role: ${user.role}`
  );

  return res.status(200).json({
    success: true,
    message: "User logged in successfully",
    token: generateToken(user.id),
    user: toPublicUser(user),
  });
};

export const changePassword = async (req, res) => {
  try {
    const { currentPassword, newPassword, confirmPassword } = req.body;
    if (!currentPassword || !newPassword || !confirmPassword) {
      return res.status(400).json({ success: false, message: "Please provide all password fields" });
    }
    if (newPassword !== confirmPassword) {
      return res.status(400).json({ success: false, message: "New password and confirm password do not match" });
    }

    const user = await getUserByLogin(req.user.urEmail || req.user.email);
    if (!user) {
      return res.status(400).json({ success: false, message: "User not found" });
    }

    const authResult = await verifyLoginPassword(currentPassword, user.password);
    if (!authResult.ok) {
      return res.status(400).json({ success: false, message: "Current password is incorrect" });
    }

    await updateUserPassword(req.user.id, newPassword);
    logSuccess(req, "Password changed successfully", "Authentication", "UPDATE", `User ID: ${req.user.id}`);
    return res.status(200).json({ success: true, message: "Password changed successfully" });
  } catch (error) {
    logFailure(req, "Failed to change password", "Authentication", "UPDATE", error.message);
    return res.status(500).json({ success: false, message: "Internal server error" });
  }
};

export const getMe = async (req, res) => {
  const user = await getUserById(req.user.id);
  if (!user) {
    return res.status(404).json({ success: false, message: "User not found" });
  }
  return res.status(200).json({ success: true, data: user });
};

export const getAllUsersController = async (req, res) => {
  try {
    const data = await getUsers({
      role: req.query.role,
      campusId: req.query.campusId,
      collegeId: req.query.collegeId,
      schoolId: req.query.schoolId,
    });
    return res.status(200).json({ success: true, message: "Users retrieved successfully", data });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const getUserByIdController = async (req, res) => {
  try {
    const user = await getUserById(req.params.id);
    if (!user) {
      return res.status(404).json({ success: false, message: "User not found" });
    }
    return res.status(200).json({ success: true, data: user });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const createUserController = async (req, res) => {
  try {
    const {
      names,
      urEmail,
      email,
      password,
      role,
      phone,
      gender,
      title,
      staffNumber,
      staffType,
      department,
      academicRank,
      campusId,
      collegeId,
      schoolId,
    } = req.body;

    if (!names || !urEmail || !password) {
      return res.status(400).json({
        success: false,
        message: "Please provide names, urEmail, and password",
      });
    }

    const userRole = role || "user";
    if (!VALID_USER_ROLES.includes(userRole)) {
      return res.status(400).json({
        success: false,
        message: `Invalid role. Allowed: ${VALID_USER_ROLES.join(", ")}`,
      });
    }

    const existing = await getUserByLogin(urEmail);
    if (existing) {
      return res.status(400).json({ success: false, message: "User with this email already exists" });
    }

    const user = await createUser({
      names,
      urEmail,
      email: email || null,
      password,
      role: userRole,
      phone: phone || null,
      gender: gender || null,
      title: title || null,
      staffNumber: staffNumber || null,
      staffType: staffType || null,
      department: department || null,
      academicRank: academicRank || null,
      campusId: campusId || null,
      collegeId: collegeId || null,
      schoolId: schoolId || null,
      active: true,
    });

    logSuccess(req, `Created user: ${names}`, "Users", "CREATE", null, user.id, "User");
    return res.status(201).json({
      success: true,
      message: "User created successfully",
      data: toPublicUser(user),
    });
  } catch (error) {
    logFailure(req, "Failed to create user", "Users", "CREATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const updateUserController = async (req, res) => {
  try {
    const allowed = [
      "names",
      "urEmail",
      "email",
      "password",
      "role",
      "phone",
      "gender",
      "title",
      "staffNumber",
      "staffType",
      "department",
      "academicRank",
      "campusId",
      "collegeId",
      "schoolId",
      "image",
      "active",
    ];
    const payload = {};
    for (const key of allowed) {
      if (req.body[key] !== undefined) payload[key] = req.body[key];
    }

    if (payload.role && !VALID_USER_ROLES.includes(payload.role)) {
      return res.status(400).json({
        success: false,
        message: `Invalid role. Allowed: ${VALID_USER_ROLES.join(", ")}`,
      });
    }

    const user = await updateUser(req.params.id, payload);
    if (!user) {
      return res.status(404).json({ success: false, message: "User not found" });
    }
    logSuccess(req, `Updated user: ${user.names}`, "Users", "UPDATE", null, user.id, "User");
    return res.status(200).json({ success: true, message: "User updated successfully", data: user });
  } catch (error) {
    logFailure(req, "Failed to update user", "Users", "UPDATE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const deleteUserController = async (req, res) => {
  try {
    const user = await softDeleteUser(req.params.id);
    if (!user) {
      return res.status(404).json({ success: false, message: "User not found" });
    }
    logSuccess(req, `Deleted user: ${user.names}`, "Users", "DELETE", null, user.id, "User");
    return res.status(200).json({ success: true, message: "User deleted successfully" });
  } catch (error) {
    logFailure(req, "Failed to delete user", "Users", "DELETE", error.message);
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const activateUserController = async (req, res) => {
  try {
    const user = await setUserActive(req.params.id, true);
    if (!user) {
      return res.status(404).json({ success: false, message: "User not found" });
    }
    logSuccess(req, `Activated user: ${user.names}`, "Users", "ACTIVATE", null, user.id, "User");
    return res.status(200).json({ success: true, message: "User activated successfully", data: user });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};

export const deactivateUserController = async (req, res) => {
  try {
    const user = await setUserActive(req.params.id, false);
    if (!user) {
      return res.status(404).json({ success: false, message: "User not found" });
    }
    logSuccess(req, `Deactivated user: ${user.names}`, "Users", "DEACTIVATE", null, user.id, "User");
    return res.status(200).json({ success: true, message: "User deactivated successfully", data: user });
  } catch (error) {
    return res.status(500).json({ success: false, message: "Something went wrong", error: error.message });
  }
};
