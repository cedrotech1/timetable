import dotenv from "dotenv";
dotenv.config();
const {
  DEV_DATABASE_NAME,
  DEV_DATABASE_USER,
  DEV_DATABASE_PASSWORD,
  DEV_DATABASE_HOST,
  DEV_DATABASE_PORT,
  PRO_DATABASE_NAME,
  PRO_DATABASE_USER,
  PRO_DATABASE_PASSWORD,
  PRO_DATABASE_HOST,
  PRO_DATABASE_PORT,
} = process.env;

/** Shared pool — default Sequelize max=5 collapses under student intake load. */
function dbPool() {
  return {
    max: parseInt(process.env.DB_POOL_MAX || '40', 10),
    min: parseInt(process.env.DB_POOL_MIN || '2', 10),
    acquire: parseInt(process.env.DB_POOL_ACQUIRE_MS || '60000', 10),
    idle: parseInt(process.env.DB_POOL_IDLE_MS || '10000', 10),
  };
}

module.exports = {
  development: {
    username: DEV_DATABASE_USER,
    password: DEV_DATABASE_PASSWORD,
    database: DEV_DATABASE_NAME,
    host: DEV_DATABASE_HOST,
    port: DEV_DATABASE_PORT,
    dialect: "postgres",
    pool: dbPool(),
  },
  uat: {
    username: process.env.UAT_DATABASE_USER || DEV_DATABASE_USER,
    password: process.env.UAT_DATABASE_PASSWORD || DEV_DATABASE_PASSWORD,
    database: process.env.UAT_DATABASE_NAME || "ur_timetable_uat",
    host: process.env.UAT_DATABASE_HOST || DEV_DATABASE_HOST,
    port: process.env.UAT_DATABASE_PORT || DEV_DATABASE_PORT,
    dialect: "postgres",
    pool: dbPool(),
  },
  production: {
    username: PRO_DATABASE_USER,
    password: PRO_DATABASE_PASSWORD,
    database: PRO_DATABASE_NAME,
    host: PRO_DATABASE_HOST,
    port: PRO_DATABASE_PORT,
    dialect: "postgres",
    pool: dbPool(),
    dialectOptions:
      PRO_DATABASE_HOST &&
      !["localhost", "127.0.0.1"].includes(PRO_DATABASE_HOST)
        ? {
            ssl: {
              require: true,
              rejectUnauthorized: true,
            },
          }
        : {},
  },
};

