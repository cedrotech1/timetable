"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class TimetableLecturer extends Model {
    static associate(models) {
      TimetableLecturer.belongsTo(models.Timetable, { foreignKey: "timetableId", as: "timetable" });
      TimetableLecturer.belongsTo(models.Users, { foreignKey: "lecturerId", as: "lecturer" });
    }
  }
  TimetableLecturer.init(
    {
      timetableId: { type: DataTypes.INTEGER, allowNull: false },
      lecturerId: { type: DataTypes.INTEGER, allowNull: false },
    },
    { sequelize, modelName: "TimetableLecturer", tableName: "timetable_lecturers" }
  );
  return TimetableLecturer;
};
