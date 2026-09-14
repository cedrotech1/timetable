const express = require("express");
const dotenv = require("dotenv");
const bodyParser = require("body-parser");
const path = require("path");

const router = require("./routers/index.js");
const {
  helmetMiddleware,
  cors,
  hpp,
  apiLimiter,
} = require("./middlewares/security.js");
const { secureUploads } = require("./middlewares/secureUploads.js");
const { errorHandler } = require("./middlewares/errorHandler.js");
const { requestAuditMiddleware } = require("./middlewares/auditLogger.js");

dotenv.config();

const app = express();

app.set("trust proxy", 1);
app.disable("x-powered-by");

app.use(helmetMiddleware);
app.use(cors);
app.use(hpp);
app.use(requestAuditMiddleware);
app.use(apiLimiter);

app.use(bodyParser.urlencoded({ extended: false, limit: "10mb" }));
app.use(bodyParser.json({ limit: "10mb" }));

app.use("/uploads", secureUploads(path.join(process.cwd(), "uploads")));
// Same files under API prefix so clients can use /api/v1/uploads when root /uploads is blocked
app.use("/api/v1/uploads", secureUploads(path.join(process.cwd(), "uploads")));

app.use("/api/v1", router);

app.get("*", (req, res) => {
  res.status(404).json({
    success: false,
    message: "NOT_FOUND",
    code: "NOT_FOUND",
  });
});

app.use(errorHandler);

module.exports = app;
