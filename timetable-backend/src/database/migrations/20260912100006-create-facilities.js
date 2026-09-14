'use strict';

module.exports = {
  async up(queryInterface, Sequelize) {
    await queryInterface.createTable('facilities', {
      id: {
        allowNull: false,
        autoIncrement: true,
        primaryKey: true,
        type: Sequelize.INTEGER,
      },
      name: {
        type: Sequelize.STRING(100),
        allowNull: false,
      },
      name2: {
        type: Sequelize.STRING(100),
        allowNull: true,
        comment: 'Alternate / display name',
      },
      type: {
        type: Sequelize.STRING(50),
        allowNull: true,
        comment: 'e.g. CLASSROOM, LAB, HALL',
      },
      capacity: {
        type: Sequelize.INTEGER,
        allowNull: true,
      },
      campusId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: {
          model: 'campuses',
          key: 'id',
        },
        onUpdate: 'CASCADE',
        onDelete: 'RESTRICT',
      },
      site: {
        type: Sequelize.STRING(100),
        allowNull: true,
        comment: 'Site / location label within campus (from PHP site id/name)',
      },
      buildName: {
        type: Sequelize.STRING(100),
        allowNull: true,
      },
      buildCode: {
        type: Sequelize.STRING(100),
        allowNull: true,
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

    await queryInterface.addIndex('facilities', ['campusId']);
    await queryInterface.addIndex('facilities', ['type']);
    await queryInterface.addIndex('facilities', ['name', 'campusId']);
  },

  async down(queryInterface) {
    await queryInterface.dropTable('facilities');
  },
};
