import { loadAppEnv } from "./config/loadEnv.js";
loadAppEnv();

import app from "./app.js";
import { validateEnv } from "./config/validateEnv.js";
import { ensureUploadDirs } from "./utils/uploadPaths.js";

validateEnv();
ensureUploadDirs();

const PORT = process.env.PORT || 5000;
const HOST = process.env.HOST || "127.0.0.1";

app.listen(PORT, HOST, () => {
  console.log(`Server is running on http://${HOST}:${PORT} [${process.env.NODE_ENV || "development"}]`);
});
