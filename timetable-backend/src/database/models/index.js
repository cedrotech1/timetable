"use strict";

import { readdirSync } from "fs";
import { basename as _basename, join } from "path";
import { Sequelize } from "sequelize";
import { loadAppEnv } from "../../config/loadEnv.js";

loadAppEnv(join(__dirname, "../../.."));

const basename = _basename(__filename);
const env = process.env.NODE_ENV;
const config = require("../config/config.js")[env];

const db = {};

const sequelize = new Sequelize(
  config.database,
  config.username,
  config.password,
  config
);

readdirSync(__dirname)
  .filter((file) => {
    return (
      file.indexOf(".") !== 0 &&
      file !== basename &&
      file.slice(-3) === ".js" &&
      file !== "index.js"
    );
  })
  .forEach((file) => {
    const model = sequelize.import(join(__dirname, file));
    db[model.name] = model;
  });

Object.keys(db).forEach((modelName) => {
  if (db[modelName].associate) {
    db[modelName].associate(db);
  }
});

db.sequelize = sequelize;
db.Sequelize = Sequelize;

export default db;
