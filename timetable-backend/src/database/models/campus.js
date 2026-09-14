"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class Campus extends Model {
    static associate(models) {
      Campus.hasMany(models.Facility, { foreignKey: "campusId", as: "facilities" });
      Campus.hasMany(models.Users, { foreignKey: "campusId", as: "users" });
      Campus.hasMany(models.Intake, { foreignKey: "campusId", as: "intakes" });
    }
  }

  Campus.init(
    {
      name: {
        type: DataTypes.STRING(100),
        allowNull: false,
        unique: true,
      },
    },
    {
      sequelize,
      modelName: "Campus",
      tableName: "campuses",
    }
  );

  return Campus;
};
