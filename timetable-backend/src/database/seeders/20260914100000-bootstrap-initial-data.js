'use strict';

const bcrypt = require('bcryptjs');

/**
 * Bootstrap initial data when PHP SQL dump is not available.
 * Safe to re-run: skips if campuses already exist.
 */
module.exports = {
  async up(queryInterface) {
    const [rows] = await queryInterface.sequelize.query(
      'SELECT COUNT(*)::int AS count FROM campuses'
    );
    if (rows[0].count > 0) {
      console.log('Seed skipped: data already present.');
      return;
    }

    const now = new Date();
    const passwordHash = await bcrypt.hash(
      process.env.SEED_ADMIN_PASSWORD || 'Admin@123',
      10
    );

    await queryInterface.bulkInsert('campuses', [
      { id: 1, name: 'Nyarugenge', createdAt: now, updatedAt: now },
      { id: 2, name: 'Huye', createdAt: now, updatedAt: now },
      { id: 3, name: 'Remera', createdAt: now, updatedAt: now },
    ]);

    await queryInterface.bulkInsert('colleges', [
      {
        id: 1,
        name: 'CST',
        fullName: 'College of Science and Technology',
        createdAt: now,
        updatedAt: now,
      },
      {
        id: 2,
        name: 'CBE',
        fullName: 'College of Business and Economics',
        createdAt: now,
        updatedAt: now,
      },
    ]);

    await queryInterface.bulkInsert('schools', [
      {
        id: 1,
        name: 'School of ICT',
        collegeId: 1,
        createdAt: now,
        updatedAt: now,
      },
      {
        id: 2,
        name: 'School of Engineering',
        collegeId: 1,
        createdAt: now,
        updatedAt: now,
      },
      {
        id: 3,
        name: 'School of Business',
        collegeId: 2,
        createdAt: now,
        updatedAt: now,
      },
    ]);

    await queryInterface.bulkInsert('programs', [
      {
        id: 1,
        name: 'Computer Science',
        code: 'CS',
        schoolId: 1,
        createdAt: now,
        updatedAt: now,
      },
      {
        id: 2,
        name: 'Information Technology',
        code: 'IT',
        schoolId: 1,
        createdAt: now,
        updatedAt: now,
      },
      {
        id: 3,
        name: 'Civil Engineering',
        code: 'CE',
        schoolId: 2,
        createdAt: now,
        updatedAt: now,
      },
    ]);

    await queryInterface.bulkInsert('modules', [
      {
        id: 1,
        code: 'CSC1101',
        name: 'Introduction to Programming',
        credits: 10,
        year: 1,
        semester: '1',
        programId: 1,
        createdAt: now,
        updatedAt: now,
      },
      {
        id: 2,
        code: 'CSC1201',
        name: 'Data Structures',
        credits: 10,
        year: 1,
        semester: '2',
        programId: 1,
        createdAt: now,
        updatedAt: now,
      },
      {
        id: 3,
        code: 'ICT1101',
        name: 'Computer Fundamentals',
        credits: 10,
        year: 1,
        semester: '1',
        programId: 2,
        createdAt: now,
        updatedAt: now,
      },
    ]);

    await queryInterface.bulkInsert('facilities', [
      {
        id: 1,
        name: 'Lab A101',
        name2: null,
        type: 'LAB',
        capacity: 40,
        campusId: 1,
        site: null,
        buildName: null,
        buildCode: null,
        createdAt: now,
        updatedAt: now,
      },
      {
        id: 2,
        name: 'Hall B201',
        name2: null,
        type: 'HALL',
        capacity: 120,
        campusId: 1,
        site: null,
        buildName: null,
        buildCode: null,
        createdAt: now,
        updatedAt: now,
      },
      {
        id: 3,
        name: 'Room C10',
        name2: null,
        type: 'CLASSROOM',
        capacity: 50,
        campusId: 2,
        site: null,
        buildName: null,
        buildCode: null,
        createdAt: now,
        updatedAt: now,
      },
    ]);

    await queryInterface.bulkInsert('users', [
      {
        id: 1,
        staffNumber: 'ADMIN001',
        names: 'System Administrator',
        collegeId: null,
        campusId: 1,
        schoolId: null,
        staffType: 'Administrative',
        department: 'ICT',
        academicRank: null,
        role: 'admin',
        title: 'Mr',
        email: 'administrator@ur.ac.rw',
        urEmail: 'administrator@ur.ac.rw',
        phone: null,
        gender: null,
        password: passwordHash,
        resetcode: null,
        image: null,
        active: true,
        deleted: 'no',
        createdAt: now,
        updatedAt: now,
      },
      {
        id: 2,
        staffNumber: 'LEC001',
        names: 'Demo Lecturer',
        collegeId: 1,
        campusId: 1,
        schoolId: 1,
        staffType: 'Academic',
        department: 'ICT',
        academicRank: 'Lecturer',
        role: 'lecturer',
        title: 'Dr',
        email: 'lecturer@ur.ac.rw',
        urEmail: 'lecturer@ur.ac.rw',
        phone: null,
        gender: null,
        password: passwordHash,
        resetcode: null,
        image: null,
        active: true,
        deleted: 'no',
        createdAt: now,
        updatedAt: now,
      },
    ]);

    await queryInterface.bulkInsert('academic_years', [
      { id: 1, yearLabel: '2025-2026', createdAt: now, updatedAt: now },
    ]);

    await queryInterface.bulkInsert('timetable_settings', [
      {
        id: 1,
        status: 'live',
        academicYearId: 1,
        semester: '1',
        updatedBy: 1,
        createdAt: now,
        updatedAt: now,
      },
    ]);

    // Keep Postgres sequences in sync after explicit ids
    for (const table of [
      'campuses',
      'colleges',
      'schools',
      'programs',
      'modules',
      'facilities',
      'users',
      'academic_years',
      'timetable_settings',
    ]) {
      await queryInterface.sequelize.query(
        `SELECT setval(pg_get_serial_sequence('${table}', 'id'), COALESCE((SELECT MAX(id) FROM ${table}), 1))`
      );
    }

    console.log('Bootstrap seed complete.');
    console.log('  Admin: administrator@ur.ac.rw / Admin@123');
    console.log('  Lecturer: lecturer@ur.ac.rw / Admin@123');
  },

  async down(queryInterface) {
    await queryInterface.bulkDelete('timetable_settings', null, {});
    await queryInterface.bulkDelete('academic_years', null, {});
    await queryInterface.bulkDelete('users', null, {});
    await queryInterface.bulkDelete('facilities', null, {});
    await queryInterface.bulkDelete('modules', null, {});
    await queryInterface.bulkDelete('programs', null, {});
    await queryInterface.bulkDelete('schools', null, {});
    await queryInterface.bulkDelete('colleges', null, {});
    await queryInterface.bulkDelete('campuses', null, {});
  },
};
