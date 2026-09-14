"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class Facility extends Model {
    static associate(models) {
      Facility.belongsTo(models.Campus, { foreignKey: "campusId", as: "campus" });
      Facility.hasMany(models.Timetable, { foreignKey: "facilityId", as: "timetables" });
    }
  }

  Facility.init(
    {
      name: {
        type: DataTypes.STRING(100),
        allowNull: false,
      },
      name2: {
        type: DataTypes.STRING(100),
        allowNull: true,
      },
      type: {
        type: DataTypes.STRING(50),
        allowNull: true,
      },
      capacity: {
        type: DataTypes.INTEGER,
        allowNull: true,
      },
      campusId: {
        type: DataTypes.INTEGER,
        allowNull: false,
      },
      site: {
        type: DataTypes.STRING(100),
        allowNull: true,
      },
      buildName: {
        type: DataTypes.STRING(100),
        allowNull: true,
      },
      buildCode: {
        type: DataTypes.STRING(100),
        allowNull: true,
      },
    },
    {
      sequelize,
      modelName: "Facility",
      tableName: "facilities",
    }
  );

  return Facility;
};
