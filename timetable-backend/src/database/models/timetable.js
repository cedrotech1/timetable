"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class Timetable extends Model {
    static associate(models) {
      Timetable.belongsTo(models.Module, { foreignKey: "moduleId", as: "module" });
      Timetable.belongsTo(models.Facility, { foreignKey: "facilityId", as: "facility" });
      Timetable.belongsTo(models.AcademicYear, { foreignKey: "academicYearId", as: "academicYear" });
      Timetable.belongsTo(models.Users, { foreignKey: "leaderLecturerId", as: "leader" });
      Timetable.belongsTo(models.Users, { foreignKey: "createdBy", as: "creator" });
      Timetable.belongsTo(models.Users, { foreignKey: "approvedBy", as: "approver" });
      Timetable.hasMany(models.TimetableSession, { foreignKey: "timetableId", as: "sessions" });
      Timetable.hasMany(models.TimetableGroup, { foreignKey: "timetableId", as: "timetableGroups" });
      Timetable.hasMany(models.TimetableLecturer, { foreignKey: "timetableId", as: "timetableLecturers" });
      Timetable.belongsToMany(models.StudentGroup, {
        through: models.TimetableGroup,
        foreignKey: "timetableId",
        otherKey: "groupId",
        as: "groups",
      });
    }
  }
  Timetable.init(
    {
      moduleId: { type: DataTypes.INTEGER, allowNull: false },
      leaderLecturerId: { type: DataTypes.INTEGER, allowNull: true },
      facilityId: { type: DataTypes.INTEGER, allowNull: false },
      semester: { type: DataTypes.STRING(10), allowNull: false },
      academicYearId: { type: DataTypes.INTEGER, allowNull: false },
      status: { type: DataTypes.STRING(50), allowNull: false, defaultValue: "pending" },
      approvedBy: { type: DataTypes.INTEGER, allowNull: true },
      createdBy: { type: DataTypes.INTEGER, allowNull: false },
    },
    { sequelize, modelName: "Timetable", tableName: "timetables" }
  );
  return Timetable;
};
