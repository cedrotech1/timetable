"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class College extends Model {
    static associate(models) {
      College.hasMany(models.School, { foreignKey: "collegeId", as: "schools" });
      College.hasMany(models.Users, { foreignKey: "collegeId", as: "users" });
    }
  }

  College.init(
    {
      name: {
        type: DataTypes.STRING(100),
        allowNull: false,
        unique: true,
      },
      fullName: {
        type: DataTypes.STRING(255),
        allowNull: false,
      },
    },
    {
      sequelize,
      modelName: "College",
      tableName: "colleges",
    }
  );

  return College;
};
