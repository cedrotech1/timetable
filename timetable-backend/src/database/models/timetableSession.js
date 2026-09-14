"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class TimetableSession extends Model {
    static associate(models) {
      TimetableSession.belongsTo(models.Timetable, { foreignKey: "timetableId", as: "timetable" });
    }
  }
  TimetableSession.init(
    {
      timetableId: { type: DataTypes.INTEGER, allowNull: false },
      day: { type: DataTypes.STRING(20), allowNull: false },
      startTime: { type: DataTypes.TIME, allowNull: false },
      endTime: { type: DataTypes.TIME, allowNull: false },
    },
    { sequelize, modelName: "TimetableSession", tableName: "timetable_sessions" }
  );
  return TimetableSession;
};
