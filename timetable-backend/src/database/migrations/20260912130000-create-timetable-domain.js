'use strict';

module.exports = {
  async up(queryInterface, Sequelize) {
    await queryInterface.createTable('academic_years', {
      id: { allowNull: false, autoIncrement: true, primaryKey: true, type: Sequelize.INTEGER },
      yearLabel: { type: Sequelize.STRING(20), allowNull: false, unique: true },
      createdAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
      updatedAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
    });

    await queryInterface.createTable('timetable_settings', {
      id: { allowNull: false, autoIncrement: true, primaryKey: true, type: Sequelize.INTEGER },
      status: { type: Sequelize.STRING(50), allowNull: false, defaultValue: 'live' },
      academicYearId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'academic_years', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'RESTRICT',
      },
      semester: { type: Sequelize.STRING(20), allowNull: false, defaultValue: '1' },
      updatedBy: { type: Sequelize.INTEGER, allowNull: true },
      createdAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
      updatedAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
    });

    await queryInterface.createTable('intakes', {
      id: { allowNull: false, autoIncrement: true, primaryKey: true, type: Sequelize.INTEGER },
      year: { type: Sequelize.INTEGER, allowNull: true },
      month: { type: Sequelize.INTEGER, allowNull: true },
      size: { type: Sequelize.INTEGER, allowNull: true },
      yearOfStudy: { type: Sequelize.INTEGER, allowNull: false },
      programId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'programs', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'RESTRICT',
      },
      campusId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'campuses', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'RESTRICT',
      },
      createdAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
      updatedAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
    });
    await queryInterface.addIndex('intakes', ['programId', 'yearOfStudy', 'campusId']);

    await queryInterface.createTable('student_groups', {
      id: { allowNull: false, autoIncrement: true, primaryKey: true, type: Sequelize.INTEGER },
      name: { type: Sequelize.STRING(50), allowNull: false },
      size: { type: Sequelize.INTEGER, allowNull: true },
      intakeId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'intakes', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'CASCADE',
      },
      createdAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
      updatedAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
    });
    await queryInterface.addIndex('student_groups', ['intakeId']);

    await queryInterface.createTable('timetables', {
      id: { allowNull: false, autoIncrement: true, primaryKey: true, type: Sequelize.INTEGER },
      moduleId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'modules', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'RESTRICT',
      },
      leaderLecturerId: {
        type: Sequelize.INTEGER,
        allowNull: true,
        references: { model: 'users', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'SET NULL',
      },
      facilityId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'facilities', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'RESTRICT',
      },
      semester: { type: Sequelize.STRING(10), allowNull: false },
      academicYearId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'academic_years', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'RESTRICT',
      },
      status: { type: Sequelize.STRING(50), allowNull: false, defaultValue: 'pending' },
      approvedBy: {
        type: Sequelize.INTEGER,
        allowNull: true,
        references: { model: 'users', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'SET NULL',
      },
      createdBy: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'users', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'RESTRICT',
      },
      createdAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
      updatedAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
    });
    await queryInterface.addIndex('timetables', ['academicYearId', 'semester', 'status']);
    await queryInterface.addIndex('timetables', ['facilityId']);
    await queryInterface.addIndex('timetables', ['moduleId']);

    await queryInterface.createTable('timetable_sessions', {
      id: { allowNull: false, autoIncrement: true, primaryKey: true, type: Sequelize.INTEGER },
      timetableId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'timetables', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'CASCADE',
      },
      day: { type: Sequelize.STRING(20), allowNull: false },
      startTime: { type: Sequelize.TIME, allowNull: false },
      endTime: { type: Sequelize.TIME, allowNull: false },
      createdAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
      updatedAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
    });
    await queryInterface.addIndex('timetable_sessions', ['timetableId', 'day']);

    await queryInterface.createTable('timetable_groups', {
      id: { allowNull: false, autoIncrement: true, primaryKey: true, type: Sequelize.INTEGER },
      timetableId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'timetables', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'CASCADE',
      },
      groupId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'student_groups', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'RESTRICT',
      },
      createdAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
      updatedAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
    });
    await queryInterface.addIndex('timetable_groups', ['timetableId', 'groupId'], { unique: true });
    await queryInterface.addIndex('timetable_groups', ['groupId']);

    await queryInterface.createTable('timetable_lecturers', {
      id: { allowNull: false, autoIncrement: true, primaryKey: true, type: Sequelize.INTEGER },
      timetableId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'timetables', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'CASCADE',
      },
      lecturerId: {
        type: Sequelize.INTEGER,
        allowNull: false,
        references: { model: 'users', key: 'id' },
        onUpdate: 'CASCADE',
        onDelete: 'CASCADE',
      },
      createdAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
      updatedAt: { allowNull: false, type: Sequelize.DATE, defaultValue: Sequelize.literal('CURRENT_TIMESTAMP') },
    });
    await queryInterface.addIndex('timetable_lecturers', ['timetableId', 'lecturerId'], { unique: true });
  },

  async down(queryInterface) {
    await queryInterface.dropTable('timetable_lecturers');
    await queryInterface.dropTable('timetable_groups');
    await queryInterface.dropTable('timetable_sessions');
    await queryInterface.dropTable('timetables');
    await queryInterface.dropTable('student_groups');
    await queryInterface.dropTable('intakes');
    await queryInterface.dropTable('timetable_settings');
    await queryInterface.dropTable('academic_years');
  },
};
