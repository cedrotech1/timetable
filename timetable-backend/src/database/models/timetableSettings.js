"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class TimetableSettings extends Model {
    static associate(models) {
      TimetableSettings.belongsTo(models.AcademicYear, { foreignKey: "academicYearId", as: "academicYear" });
    }
  }
  TimetableSettings.init(
    {
      status: { type: DataTypes.STRING(50), allowNull: false, defaultValue: "live" },
      academicYearId: { type: DataTypes.INTEGER, allowNull: false },
      semester: { type: DataTypes.STRING(20), allowNull: false, defaultValue: "1" },
      updatedBy: { type: DataTypes.INTEGER, allowNull: true },
    },
    { sequelize, modelName: "TimetableSettings", tableName: "timetable_settings" }
  );
  return TimetableSettings;
};
