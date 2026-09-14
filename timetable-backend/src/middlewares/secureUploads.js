const path = require("path");
const fs = require("fs");
const jwt = require("jsonwebtoken");

/** Serves upload files to authenticated users (JWT Bearer or ?token=). */
function secureUploads(uploadRoot) {
  const root = path.resolve(uploadRoot);

  return (req, res) => {
    const bearer = req.headers.authorization;
    const queryToken = req.query.token;
    let token = null;

    if (bearer && bearer.startsWith("Bearer ")) {
      token = bearer.split(" ")[1];
    } else if (typeof queryToken === "string" && queryToken.length > 0) {
      token = queryToken;
    }

    if (!token) {
      return res.status(401).json({ success: false, message: "Not authorized" });
    }

    try {
      jwt.verify(token, process.env.JWT_SECRET);
    } catch {
      return res.status(401).json({ success: false, message: "Not authorized" });
    }

    const relative = req.path.replace(/^\/+/, "");
    const direct = path.resolve(path.join(root, relative));

    if (!(direct.startsWith(root + path.sep) || direct === root)) {
      return res.status(400).json({ success: false, message: "Invalid path" });
    }

    if (!fs.existsSync(direct) || !fs.statSync(direct).isFile()) {
      return res.status(404).json({ success: false, message: "File not found" });
    }

    return res.sendFile(direct);
  };
}

module.exports = { secureUploads };
