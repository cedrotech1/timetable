require("dotenv").config();
const bcrypt = require("bcryptjs");
const { Client } = require("pg");

(async () => {
  const plain = process.env.SEED_USER_PASSWORD || "23122312";
  const hash = await bcrypt.hash(plain, 10);
  const client = new Client({
    host: process.env.DEV_DATABASE_HOST || "127.0.0.1",
    port: Number(process.env.DEV_DATABASE_PORT || 5432),
    user: process.env.DEV_DATABASE_USER || "postgres",
    password: process.env.DEV_DATABASE_PASSWORD || "",
    database: process.env.DEV_DATABASE_NAME || "ur_timetable",
  });

  await client.connect();
  const result = await client.query(
    `UPDATE users SET password = $1, "updatedAt" = NOW()`,
    [hash]
  );
  console.log(`Updated ${result.rowCount} users — password set to: ${plain}`);
  await client.end();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
