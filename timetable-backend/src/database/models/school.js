"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class School extends Model {
    static associate(models) {
      School.belongsTo(models.College, { foreignKey: "collegeId", as: "college" });
      School.hasMany(models.Program, { foreignKey: "schoolId", as: "programs" });
      School.hasMany(models.Users, { foreignKey: "schoolId", as: "users" });
    }
  }

  School.init(
    {
      name: {
        type: DataTypes.STRING(150),
        allowNull: false,
      },
      collegeId: {
        type: DataTypes.INTEGER,
        allowNull: false,
      },
    },
    {
      sequelize,
      modelName: "School",
      tableName: "schools",
    }
  );

  return School;
};
