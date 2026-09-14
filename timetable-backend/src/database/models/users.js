"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class Users extends Model {
    static associate(models) {
      Users.belongsTo(models.Campus, { foreignKey: "campusId", as: "campus" });
      Users.belongsTo(models.College, { foreignKey: "collegeId", as: "college" });
      Users.belongsTo(models.School, { foreignKey: "schoolId", as: "school" });
      Users.hasMany(models.ActivityLog, { foreignKey: "userId", as: "activityLogs" });
    }
  }

  Users.init(
    {
      staffNumber: {
        type: DataTypes.STRING(50),
        allowNull: true,
        unique: true,
      },
      names: {
        type: DataTypes.STRING(255),
        allowNull: false,
      },
      collegeId: {
        type: DataTypes.INTEGER,
        allowNull: true,
      },
      campusId: {
        type: DataTypes.INTEGER,
        allowNull: true,
      },
      schoolId: {
        type: DataTypes.INTEGER,
        allowNull: true,
      },
      staffType: {
        type: DataTypes.STRING(100),
        allowNull: true,
      },
      department: {
        type: DataTypes.STRING(150),
        allowNull: true,
      },
      academicRank: {
        type: DataTypes.STRING(100),
        allowNull: true,
      },
      role: {
        type: DataTypes.STRING(100),
        allowNull: false,
        defaultValue: "user",
      },
      title: {
        type: DataTypes.STRING(50),
        allowNull: true,
      },
      email: {
        type: DataTypes.STRING(255),
        allowNull: true,
      },
      urEmail: {
        type: DataTypes.STRING(255),
        allowNull: false,
        unique: true,
      },
      phone: {
        type: DataTypes.STRING(30),
        allowNull: true,
      },
      gender: {
        type: DataTypes.STRING(20),
        allowNull: true,
      },
      password: {
        type: DataTypes.STRING(255),
        allowNull: false,
      },
      resetcode: {
        type: DataTypes.STRING(20),
        allowNull: true,
      },
      image: {
        type: DataTypes.STRING(255),
        allowNull: true,
        defaultValue: "uploads/profile/icon1.png",
      },
      active: {
        type: DataTypes.BOOLEAN,
        allowNull: false,
        defaultValue: true,
      },
      deleted: {
        type: DataTypes.STRING(10),
        allowNull: false,
        defaultValue: "no",
      },
    },
    {
      sequelize,
      modelName: "Users",
      tableName: "users",
    }
  );

  return Users;
};
