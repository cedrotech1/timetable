"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class Module extends Model {
    static associate(models) {
      Module.belongsTo(models.Program, { foreignKey: "programId", as: "program" });
      Module.hasMany(models.Timetable, { foreignKey: "moduleId", as: "timetables" });
    }
  }

  Module.init(
    {
      name: {
        type: DataTypes.STRING(255),
        allowNull: false,
      },
      code: {
        type: DataTypes.STRING(50),
        allowNull: true,
      },
      credits: {
        type: DataTypes.INTEGER,
        allowNull: false,
        defaultValue: 0,
      },
      year: {
        type: DataTypes.INTEGER,
        allowNull: false,
      },
      semester: {
        type: DataTypes.STRING(20),
        allowNull: false,
      },
      programId: {
        type: DataTypes.INTEGER,
        allowNull: false,
      },
    },
    {
      sequelize,
      modelName: "Module",
      tableName: "modules",
    }
  );

  return Module;
};
