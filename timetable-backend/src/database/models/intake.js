"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class Intake extends Model {
    static associate(models) {
      Intake.belongsTo(models.Program, { foreignKey: "programId", as: "program" });
      Intake.belongsTo(models.Campus, { foreignKey: "campusId", as: "campus" });
      Intake.hasMany(models.StudentGroup, { foreignKey: "intakeId", as: "groups" });
    }
  }
  Intake.init(
    {
      year: { type: DataTypes.INTEGER, allowNull: true },
      month: { type: DataTypes.INTEGER, allowNull: true },
      size: { type: DataTypes.INTEGER, allowNull: true },
      yearOfStudy: { type: DataTypes.INTEGER, allowNull: false },
      programId: { type: DataTypes.INTEGER, allowNull: false },
      campusId: { type: DataTypes.INTEGER, allowNull: false },
    },
    { sequelize, modelName: "Intake", tableName: "intakes" }
  );
  return Intake;
};
