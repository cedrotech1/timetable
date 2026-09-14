"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class StudentGroup extends Model {
    static associate(models) {
      StudentGroup.belongsTo(models.Intake, { foreignKey: "intakeId", as: "intake" });
      StudentGroup.belongsToMany(models.Timetable, {
        through: models.TimetableGroup,
        foreignKey: "groupId",
        otherKey: "timetableId",
        as: "timetables",
      });
    }
  }
  StudentGroup.init(
    {
      name: { type: DataTypes.STRING(50), allowNull: false },
      size: { type: DataTypes.INTEGER, allowNull: true },
      intakeId: { type: DataTypes.INTEGER, allowNull: false },
    },
    { sequelize, modelName: "StudentGroup", tableName: "student_groups" }
  );
  return StudentGroup;
};
