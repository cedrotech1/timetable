"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class AcademicYear extends Model {
    static associate(models) {
      AcademicYear.hasMany(models.Timetable, { foreignKey: "academicYearId", as: "timetables" });
      AcademicYear.hasMany(models.TimetableSettings, { foreignKey: "academicYearId", as: "settings" });
    }
  }
  AcademicYear.init(
    {
      yearLabel: { type: DataTypes.STRING(20), allowNull: false, unique: true },
    },
    { sequelize, modelName: "AcademicYear", tableName: "academic_years" }
  );
  return AcademicYear;
};
