const { Client } = require("pg");

(async () => {
  const c = new Client({
    host: "127.0.0.1",
    port: 5432,
    user: "postgres",
    password: "password",
    database: "postgres",
  });
  try {
    await c.connect();
    const r = await c.query(
      "SELECT 1 FROM pg_database WHERE datname = $1",
      ["ur_timetable"]
    );
    if (r.rowCount === 0) {
      await c.query("CREATE DATABASE ur_timetable");
      console.log("created ur_timetable");
    } else {
      console.log("ur_timetable already exists");
    }
  } catch (e) {
    console.error("ERR", e.message);
    process.exitCode = 1;
  } finally {
    await c.end();
  }
})();
