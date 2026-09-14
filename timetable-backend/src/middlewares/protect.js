import jwt from "jsonwebtoken";
import asyncHandler from "express-async-handler";
import db from "../database/models/index.js";

const User = db["Users"];

function getJwtSecret() {
  const secret = process.env.JWT_SECRET;
  if (!secret) {
    throw new Error("JWT_SECRET is not configured");
  }
  return secret;
}

export const protect = asyncHandler(async (req, res, next) => {
  const auth = req.headers.authorization;

  if (!auth || !auth.startsWith("Bearer ")) {
    return res.status(401).json({ success: false, message: "Not authorized, no token" });
  }

  try {
    const token = auth.split(" ")[1];
    const decoded = jwt.verify(token, getJwtSecret());

    const user = await User.findByPk(decoded.id, {
      attributes: { exclude: ["password", "resetcode"] },
    });

    if (!user) {
      return res.status(401).json({ success: false, message: "Not authorized" });
    }

    if (String(user.deleted || "").toLowerCase() === "yes") {
      return res.status(401).json({ success: false, message: "Not authorized" });
    }

    if (user.active === false || user.active === 0) {
      return res.status(403).json({ success: false, message: "Account is deactivated" });
    }

    req.user = user;
    return next();
  } catch (error) {
    console.error("JWT Verification Error:", error.message);
    return res.status(401).json({ success: false, message: "Not authorized" });
  }
});
