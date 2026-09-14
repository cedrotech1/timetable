"use strict";
const { Model } = require("sequelize");

module.exports = (sequelize, DataTypes) => {
  class ActivityLog extends Model {
    static associate(models) {
      ActivityLog.belongsTo(models.Users, { foreignKey: "userId", as: "user" });
    }
  }

  ActivityLog.init(
    {
      userId: {
        type: DataTypes.INTEGER,
        allowNull: true,
      },
      activity: {
        type: DataTypes.STRING(255),
        allowNull: false,
      },
      details: {
        type: DataTypes.TEXT,
        allowNull: true,
      },
      module: {
        type: DataTypes.STRING(100),
        allowNull: false,
      },
      action: {
        type: DataTypes.ENUM(
          "CREATE",
          "UPDATE",
          "DELETE",
          "LOGIN",
          "LOGOUT",
          "VIEW",
          "ACTIVATE",
          "DEACTIVATE"
        ),
        allowNull: false,
      },
      targetId: {
        type: DataTypes.INTEGER,
        allowNull: true,
      },
      targetType: {
        type: DataTypes.STRING(50),
        allowNull: true,
      },
      ipAddress: {
        type: DataTypes.STRING(45),
        allowNull: true,
      },
      userAgent: {
        type: DataTypes.STRING(500),
        allowNull: true,
      },
      status: {
        type: DataTypes.ENUM("SUCCESS", "FAILED", "WARNING"),
        allowNull: false,
        defaultValue: "SUCCESS",
      },
      errorMessage: {
        type: DataTypes.TEXT,
        allowNull: true,
      },
    },
    {
      sequelize,
      modelName: "ActivityLog",
      tableName: "activity_logs",
      timestamps: true,
    }
  );

  return ActivityLog;
};
