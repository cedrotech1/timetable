"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class Program extends Model {
    static associate(models) {
      Program.belongsTo(models.School, { foreignKey: "schoolId", as: "school" });
      Program.hasMany(models.Module, { foreignKey: "programId", as: "modules" });
      Program.hasMany(models.Intake, { foreignKey: "programId", as: "intakes" });
    }
  }

  Program.init(
    {
      name: {
        type: DataTypes.STRING(255),
        allowNull: false,
      },
      code: {
        type: DataTypes.STRING(50),
        allowNull: true,
      },
      schoolId: {
        type: DataTypes.INTEGER,
        allowNull: false,
      },
    },
    {
      sequelize,
      modelName: "Program",
      tableName: "programs",
    }
  );

  return Program;
};
