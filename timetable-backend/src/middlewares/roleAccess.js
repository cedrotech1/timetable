import { isAdminRole } from "../utils/roleHelpers.js";

export const ROLE_ACCESS = {
  FULL_ACCESS: ["admin"],
};

export const hasFullAccess = (userRole) => {
  return ROLE_ACCESS.FULL_ACCESS.includes(userRole);
};

export const requireAdmin = async (req, res, next) => {
  if (!req.user || !isAdminRole(req.user.role)) {
    return res.status(403).json({
      success: false,
      message: "Access denied: admin only",
    });
  }
  return next();
};

/** Allow admin (and optionally dean/registrar) to mutate org data */
export const requireManageAccess = async (req, res, next) => {
  const allowed = ["admin", "dean_office", "registrar_office"];
  if (!req.user || !allowed.includes(req.user.role)) {
    return res.status(403).json({
      success: false,
      message: "Access denied: insufficient permissions",
    });
  }
  return next();
};

export const checkRoleModificationAccess = (req, res, next) => {
  if (!req.user || !hasFullAccess(req.user.role)) {
    return res.status(403).json({
      success: false,
      message: "Access denied: only admin can create/update users",
    });
  }
  return next();
};
