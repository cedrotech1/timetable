/**
 * Create the Postgres database from .env if it does not exist.
 * Usage: node scripts/ensure-db.js
 */
const path = require("path");
const { Client } = require("pg");
const dotenv = require("dotenv");

dotenv.config({ path: path.join(__dirname, "..", ".env") });

const host = process.env.DEV_DATABASE_HOST || "127.0.0.1";
const port = Number(process.env.DEV_DATABASE_PORT || 5432);
const user = process.env.DEV_DATABASE_USER || "postgres";
const password = process.env.DEV_DATABASE_PASSWORD || "password";
const dbName = process.env.DEV_DATABASE_NAME || "ur_timetable";

(async () => {
  const c = new Client({
    host,
    port,
    user,
    password,
    database: "postgres",
  });

  try {
    await c.connect();
    const r = await c.query("SELECT 1 FROM pg_database WHERE datname = $1", [dbName]);
    if (r.rowCount === 0) {
      await c.query(`CREATE DATABASE "${dbName.replace(/"/g, '""')}"`);
      console.log(`Created database: ${dbName}`);
    } else {
      console.log(`Database already exists: ${dbName}`);
    }
  } catch (e) {
    console.error("Could not create/check database.");
    console.error("  Make sure PostgreSQL is running and .env credentials match.");
    console.error("  ERR:", e.message);
    process.exitCode = 1;
  } finally {
    await c.end();
  }
})();
