'use strict';

module.exports = {
  async up(queryInterface, Sequelize) {
    await queryInterface.createTable('users', {
      id: {
        allowNull: false,
        autoIncrement: true,
        primaryKey: true,
        type: Sequelize.INTEGER,
      },
      staffNumber: {
        type: Sequelize.STRING(50),
        allowNull: true,
        unique: true,
      },
      names: {
        type: Sequelize.STRING(255),
        allowNull: false,
      },
      collegeId: {
        type: Sequelize.INTEGER,
        allowNull: true,
        references: {
          model: 'colleges',
          key: 'id',
        },
        onUpdate: 'CASCADE',
        onDelete: 'SET NULL',
      },
      campusId: {
        type: Sequelize.INTEGER,
        allowNull: true,
        references: {
          model: 'campuses',
          key: 'id',
        },
        onUpdate: 'CASCADE',
        onDelete: 'SET NULL',
      },
      schoolId: {
        type: Sequelize.INTEGER,
        allowNull: true,
        references: {
          model: 'schools',
          key: 'id',
        },
        onUpdate: 'CASCADE',
        onDelete: 'SET NULL',
      },
      staffType: {
        type: Sequelize.STRING(100),
        allowNull: true,
        comment: 'Academic or Administrative',
      },
      department: {
        type: Sequelize.STRING(150),
        allowNull: true,
      },
      academicRank: {
        type: Sequelize.STRING(100),
        allowNull: true,
      },
      role: {
        type: Sequelize.STRING(100),
        allowNull: false,
        defaultValue: 'user',
        comment: 'admin, dean_office, registrar_office, lecturer, user, …',
      },
      title: {
        type: Sequelize.STRING(50),
        allowNull: true,
      },
      email: {
        type: Sequelize.STRING(255),
        allowNull: true,
        comment: 'Personal email',
      },
      urEmail: {
        type: Sequelize.STRING(255),
        allowNull: false,
        unique: true,
        comment: 'University email (primary login)',
      },
      phone: {
        type: Sequelize.STRING(30),
        allowNull: true,
      },
      gender: {
        type: Sequelize.STRING(20),
        allowNull: true,
      },
      password: {
        type: Sequelize.STRING(255),
        allowNull: false,
      },
      resetcode: {
        type: Sequelize.STRING(20),
        allowNull: true,
      },
      image: {
        type: Sequelize.STRING(255),
        allowNull: true,
        defaultValue: 'uploads/profile/icon1.png',
      },
      active: {
        type: Sequelize.BOOLEAN,
        allowNull: false,
        defaultValue: true,
      },
      deleted: {
        type: Sequelize.STRING(10),
        allowNull: false,
        defaultValue: 'no',
      },
      createdAt: {
        allowNull: false,
        type: Sequelize.DATE,
        defaultValue: Sequelize.literal('CURRENT_TIMESTAMP'),
      },
      updatedAt: {
        allowNull: false,
        type: Sequelize.DATE,
        defaultValue: Sequelize.literal('CURRENT_TIMESTAMP'),
      },
    });

    await queryInterface.addIndex('users', ['role']);
    await queryInterface.addIndex('users', ['campusId']);
    await queryInterface.addIndex('users', ['collegeId']);
    await queryInterface.addIndex('users', ['schoolId']);
    await queryInterface.addIndex('users', ['email']);
  },

  async down(queryInterface) {
    await queryInterface.dropTable('users');
  },
};
