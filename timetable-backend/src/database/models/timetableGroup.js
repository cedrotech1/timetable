"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class TimetableGroup extends Model {
    static associate(models) {
      TimetableGroup.belongsTo(models.Timetable, { foreignKey: "timetableId", as: "timetable" });
      TimetableGroup.belongsTo(models.StudentGroup, { foreignKey: "groupId", as: "group" });
    }
  }
  TimetableGroup.init(
    {
      timetableId: { type: DataTypes.INTEGER, allowNull: false },
      groupId: { type: DataTypes.INTEGER, allowNull: false },
    },
    { sequelize, modelName: "TimetableGroup", tableName: "timetable_groups" }
  );
  return TimetableGroup;
};
