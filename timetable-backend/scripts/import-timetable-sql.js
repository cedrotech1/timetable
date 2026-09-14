/**
 * Import core timetable data from PHP dump timetable-v3.sql into Postgres.
 *
 * Usage:
 *   node scripts/import-timetable-sql.js
 *   node scripts/import-timetable-sql.js --sql "../timetable-php/timetable-v3.sql"
 */
const fs = require("fs");
const path = require("path");
const { Client } = require("pg");
const dotenv = require("dotenv");

dotenv.config({ path: path.join(__dirname, "..", ".env") });

const DEFAULT_SQL = path.join(
  __dirname,
  "..",
  "..",
  "timetable-php",
  "timetable-v3.sql"
);

function argValue(flag, fallback) {
  const idx = process.argv.indexOf(flag);
  if (idx >= 0 && process.argv[idx + 1]) return process.argv[idx + 1];
  return fallback;
}

/**
 * Parse MySQL INSERT blocks for a table into array of row objects.
 */
function parseInserts(sqlText, tableName) {
  const rows = [];
  const re = new RegExp(
    String.raw`INSERT INTO \`${tableName}\`\s*\(([^)]+)\)\s*VALUES\s*`,
    "gi"
  );

  let match;
  while ((match = re.exec(sqlText)) !== null) {
    const columns = match[1]
      .split(",")
      .map((c) => c.trim().replace(/`/g, ""));

    let i = re.lastIndex;
    while (i < sqlText.length) {
      while (i < sqlText.length && /\s/.test(sqlText[i])) i += 1;
      if (sqlText[i] !== "(") break;

      const { values, nextIndex } = parseTuple(sqlText, i);
      if (values.length !== columns.length) {
        throw new Error(
          `Column/value mismatch for ${tableName}: ${columns.length} cols vs ${values.length} values near ${i}`
        );
      }
      const row = {};
      columns.forEach((col, idx) => {
        row[col] = values[idx];
      });
      rows.push(row);

      i = nextIndex;
      while (i < sqlText.length && /\s/.test(sqlText[i])) i += 1;
      if (sqlText[i] === ",") {
        i += 1;
        continue;
      }
      if (sqlText[i] === ";") {
        i += 1;
        break;
      }
      break;
    }
    re.lastIndex = i;
  }

  return rows;
}

function parseTuple(text, start) {
  // start points at '('
  let i = start + 1;
  const values = [];
  while (i < text.length) {
    while (i < text.length && /\s/.test(text[i])) i += 1;

    if (text[i] === ")") {
      return { values, nextIndex: i + 1 };
    }

    if (text.slice(i, i + 4).toUpperCase() === "NULL" && /[\s,)]/.test(text[i + 4] || " ")) {
      values.push(null);
      i += 4;
    } else if (text[i] === "'" || text[i] === '"') {
      const quote = text[i];
      i += 1;
      let str = "";
      while (i < text.length) {
        const ch = text[i];
        if (ch === "\\" && i + 1 < text.length) {
          const next = text[i + 1];
          const map = { n: "\n", r: "\r", t: "\t", "0": "\0", "'": "'", '"': '"', "\\": "\\" };
          str += map[next] !== undefined ? map[next] : next;
          i += 2;
          continue;
        }
        if (ch === quote) {
          // MySQL escaped quote as ''
          if (text[i + 1] === quote) {
            str += quote;
            i += 2;
            continue;
          }
          i += 1;
          break;
        }
        str += ch;
        i += 1;
      }
      values.push(str);
    } else {
      let num = "";
      while (i < text.length && /[0-9.eE+\-]/.test(text[i])) {
        num += text[i];
        i += 1;
      }
      values.push(num.includes(".") ? Number(num) : parseInt(num, 10));
    }

    while (i < text.length && /\s/.test(text[i])) i += 1;
    if (text[i] === ",") {
      i += 1;
      continue;
    }
    if (text[i] === ")") {
      return { values, nextIndex: i + 1 };
    }
  }
  throw new Error("Unclosed tuple while parsing SQL INSERT");
}

function toIntOrNull(value) {
  if (value === null || value === undefined || value === "") return null;
  const n = parseInt(String(value).trim(), 10);
  return Number.isFinite(n) ? n : null;
}

function emptyToNull(value) {
  if (value === null || value === undefined) return null;
  const s = String(value).trim();
  return s === "" ? null : s;
}

function normalizeRole(role) {
  const r = emptyToNull(role);
  return r || "user";
}

function normalizeImage(image) {
  const img = emptyToNull(image) || "uploads/profiles/icon1.png";
  return img.replace(/uploads\/profile\//g, "uploads/profiles/");
}

async function resetSequence(client, table, column = "id") {
  await client.query(
    `SELECT setval(pg_get_serial_sequence('${table}', '${column}'), COALESCE((SELECT MAX(${column}) FROM ${table}), 1), true)`
  );
}

async function bulkInsert(client, table, columns, rows, batchSize = 200) {
  if (!rows.length) return 0;
  let inserted = 0;
  for (let start = 0; start < rows.length; start += batchSize) {
    const batch = rows.slice(start, start + batchSize);
    const values = [];
    const params = [];
    let p = 1;
    for (const row of batch) {
      const placeholders = columns.map(() => `$${p++}`);
      values.push(`(${placeholders.join(", ")})`);
      for (const col of columns) params.push(row[col]);
    }
    await client.query(
      `INSERT INTO ${table} (${columns.map((c) => `"${c}"`).join(", ")}) VALUES ${values.join(", ")}`,
      params
    );
    inserted += batch.length;
  }
  return inserted;
}

async function main() {
  const sqlPath = path.resolve(argValue("--sql", DEFAULT_SQL));
  if (!fs.existsSync(sqlPath)) {
    console.error(`SQL file not found: ${sqlPath}`);
    process.exit(1);
  }

  console.log(`Reading ${sqlPath}…`);
  const sqlText = fs.readFileSync(sqlPath, "utf8");

  const campuses = parseInserts(sqlText, "campus");
  const colleges = parseInserts(sqlText, "college");
  const schools = parseInserts(sqlText, "school");
  const programs = parseInserts(sqlText, "program");
  const modules = parseInserts(sqlText, "module");
  const facilities = parseInserts(sqlText, "facility");
  const sites = parseInserts(sqlText, "site");
  const users = parseInserts(sqlText, "users");
  const academicYears = parseInserts(sqlText, "academic_year");
  const intakes = parseInserts(sqlText, "intake");
  const studentGroups = parseInserts(sqlText, "student_group");
  const systemRows = parseInserts(sqlText, "system");

  console.log("Parsed:", {
    campuses: campuses.length,
    colleges: colleges.length,
    schools: schools.length,
    programs: programs.length,
    modules: modules.length,
    facilities: facilities.length,
    sites: sites.length,
    users: users.length,
    academicYears: academicYears.length,
    intakes: intakes.length,
    studentGroups: studentGroups.length,
  });

  const siteById = new Map(sites.map((s) => [Number(s.id), s.name]));

  const client = new Client({
    host: process.env.DEV_DATABASE_HOST || "127.0.0.1",
    port: Number(process.env.DEV_DATABASE_PORT || 5432),
    user: process.env.DEV_DATABASE_USER || "postgres",
    password: process.env.DEV_DATABASE_PASSWORD || "",
    database: process.env.DEV_DATABASE_NAME || "ur_timetable",
  });

  await client.connect();
  console.log(`Connected to ${client.database}`);

  try {
    await client.query("BEGIN");

    // Child → parent truncate order
    await client.query(`
      TRUNCATE TABLE
        timetable_lecturers,
        timetable_groups,
        timetable_sessions,
        timetables,
        student_groups,
        intakes,
        timetable_settings,
        academic_years,
        activity_logs,
        users,
        modules,
        programs,
        schools,
        facilities,
        colleges,
        campuses
      RESTART IDENTITY CASCADE
    `);

    const now = new Date();

    const campusRows = campuses.map((r) => ({
      id: Number(r.id),
      name: String(r.name).trim(),
      createdAt: now,
      updatedAt: now,
    }));
    await bulkInsert(client, "campuses", ["id", "name", "createdAt", "updatedAt"], campusRows);

    const collegeRows = colleges.map((r) => ({
      id: Number(r.id),
      name: String(r.name).trim(),
      fullName: String(r.full_name || r.name).trim(),
      createdAt: now,
      updatedAt: now,
    }));
    await bulkInsert(
      client,
      "colleges",
      ["id", "name", "fullName", "createdAt", "updatedAt"],
      collegeRows
    );

    const schoolRows = schools.map((r) => ({
      id: Number(r.id),
      name: String(r.name).trim(),
      collegeId: Number(r.college_id),
      createdAt: now,
      updatedAt: now,
    }));
    await bulkInsert(
      client,
      "schools",
      ["id", "name", "collegeId", "createdAt", "updatedAt"],
      schoolRows
    );

    const programRows = programs.map((r) => ({
      id: Number(r.id),
      name: String(r.name).trim(),
      code: emptyToNull(r.code),
      schoolId: Number(r.school_id),
      createdAt: now,
      updatedAt: now,
    }));
    await bulkInsert(
      client,
      "programs",
      ["id", "name", "code", "schoolId", "createdAt", "updatedAt"],
      programRows
    );

    const programIds = new Set(programRows.map((p) => p.id));
    const moduleRows = modules
      .filter((r) => programIds.has(Number(r.program_id)))
      .map((r) => ({
        id: Number(r.id),
        name: String(r.name || "").trim() || `Module ${r.id}`,
        code: emptyToNull(r.code),
        credits: Number(r.credits) || 0,
        year: Number(r.year) || 1,
        semester: String(r.semester ?? "1"),
        programId: Number(r.program_id),
        createdAt: now,
        updatedAt: now,
      }));
    const skippedModules = modules.length - moduleRows.length;
    await bulkInsert(
      client,
      "modules",
      ["id", "name", "code", "credits", "year", "semester", "programId", "createdAt", "updatedAt"],
      moduleRows
    );

    const campusIds = new Set(campusRows.map((c) => c.id));
    const facilityRows = facilities
      .filter((r) => campusIds.has(Number(r.campus_id)))
      .map((r) => {
        const siteId = toIntOrNull(r.site);
        const siteName = siteId != null ? siteById.get(siteId) || String(siteId) : emptyToNull(r.site);
        return {
          id: Number(r.id),
          name: String(r.name || "").trim() || `Facility ${r.id}`,
          name2: emptyToNull(r.name2),
          type: emptyToNull(r.type),
          capacity: toIntOrNull(r.capacity),
          campusId: Number(r.campus_id),
          site: siteName,
          buildName: emptyToNull(r.buildname),
          buildCode: emptyToNull(r.build_code),
          createdAt: now,
          updatedAt: now,
        };
      });
    await bulkInsert(
      client,
      "facilities",
      [
        "id",
        "name",
        "name2",
        "type",
        "capacity",
        "campusId",
        "site",
        "buildName",
        "buildCode",
        "createdAt",
        "updatedAt",
      ],
      facilityRows
    );

    const collegeIds = new Set(collegeRows.map((c) => c.id));
    const schoolIds = new Set(schoolRows.map((s) => s.id));
    const seenEmails = new Set();
    const userRows = [];
    let skippedUsers = 0;

    for (const r of users) {
      let urEmail = emptyToNull(r.ur_email);
      if (!urEmail) {
        urEmail = `user-${r.id}@import.local`;
      }
      const emailKey = urEmail.toLowerCase();
      if (seenEmails.has(emailKey)) {
        skippedUsers += 1;
        continue;
      }
      seenEmails.add(emailKey);

      const password = emptyToNull(r.password);
      if (!password) {
        skippedUsers += 1;
        continue;
      }

      const campusId = toIntOrNull(r.campus);
      const collegeId = toIntOrNull(r.college);
      const schoolId = toIntOrNull(r.school);

      userRows.push({
        id: Number(r.id),
        staffNumber: emptyToNull(r.staff_number),
        names: String(r.names || `User ${r.id}`).trim(),
        collegeId: collegeId && collegeIds.has(collegeId) ? collegeId : null,
        campusId: campusId && campusIds.has(campusId) ? campusId : null,
        schoolId: schoolId && schoolIds.has(schoolId) ? schoolId : null,
        staffType: emptyToNull(r.staff_type),
        department: emptyToNull(r.department),
        academicRank: emptyToNull(r.accademic_rank),
        role: normalizeRole(r.role),
        title: emptyToNull(r.title),
        email: emptyToNull(r.email),
        urEmail,
        phone: emptyToNull(r.phone),
        gender: emptyToNull(r.gender),
        password,
        resetcode: null,
        image: normalizeImage(r.image),
        active: r.active === 1 || r.active === true || r.active === "1",
        deleted: "no",
        createdAt: r.created_at ? new Date(r.created_at) : now,
        updatedAt: r.updated_at ? new Date(r.updated_at) : now,
      });
    }

    await bulkInsert(
      client,
      "users",
      [
        "id",
        "staffNumber",
        "names",
        "collegeId",
        "campusId",
        "schoolId",
        "staffType",
        "department",
        "academicRank",
        "role",
        "title",
        "email",
        "urEmail",
        "phone",
        "gender",
        "password",
        "resetcode",
        "image",
        "active",
        "deleted",
        "createdAt",
        "updatedAt",
      ],
      userRows,
      100
    );

    // Set a shared known password for all imported users
    const bcrypt = require("bcryptjs");
    const sharedPassword = process.env.SEED_USER_PASSWORD || "23122312";
    const passwordHash = await bcrypt.hash(sharedPassword, 10);
    const passwordUpdate = await client.query(
      `UPDATE users SET password = $1, "updatedAt" = NOW()`,
      [passwordHash]
    );
    console.log(`Set password for ${passwordUpdate.rowCount} users to: ${sharedPassword}`);

    const ayRows = (academicYears.length ? academicYears : [{ id: 1, year_label: "2025-2026" }]).map((r) => ({
      id: Number(r.id),
      yearLabel: String(r.year_label || r.yearLabel).trim(),
      createdAt: now,
      updatedAt: now,
    }));
    await bulkInsert(client, "academic_years", ["id", "yearLabel", "createdAt", "updatedAt"], ayRows);

    const sys = systemRows[0] || { accademic_year_id: ayRows[0].id, semester: "1", status: "live", userid: 2 };
    await bulkInsert(
      client,
      "timetable_settings",
      ["id", "status", "academicYearId", "semester", "updatedBy", "createdAt", "updatedAt"],
      [
        {
          id: 1,
          status: String(sys.status || "live"),
          academicYearId: Number(sys.accademic_year_id || ayRows[0].id),
          semester: String(sys.semester || "1"),
          updatedBy: toIntOrNull(sys.userid),
          createdAt: now,
          updatedAt: now,
        },
      ]
    );

    const validProgramIds = new Set(programRows.map((p) => p.id));
    const intakeRows = intakes
      .filter((r) => validProgramIds.has(Number(r.program_id)) && campusIds.has(Number(r.campus_id)))
      .map((r) => ({
        id: Number(r.id),
        year: toIntOrNull(r.year),
        month: toIntOrNull(r.month),
        size: toIntOrNull(r.size),
        yearOfStudy: Number(r.year_of_study) || 1,
        programId: Number(r.program_id),
        campusId: Number(r.campus_id),
        createdAt: now,
        updatedAt: now,
      }));
    await bulkInsert(
      client,
      "intakes",
      ["id", "year", "month", "size", "yearOfStudy", "programId", "campusId", "createdAt", "updatedAt"],
      intakeRows
    );

    const intakeIds = new Set(intakeRows.map((i) => i.id));
    const groupRows = studentGroups
      .filter((r) => intakeIds.has(Number(r.intake_id)))
      .map((r) => ({
        id: Number(r.id),
        name: String(r.name || "Group 1").trim(),
        size: toIntOrNull(r.size),
        intakeId: Number(r.intake_id),
        createdAt: now,
        updatedAt: now,
      }));
    await bulkInsert(
      client,
      "student_groups",
      ["id", "name", "size", "intakeId", "createdAt", "updatedAt"],
      groupRows
    );

    for (const table of [
      "campuses",
      "colleges",
      "schools",
      "programs",
      "modules",
      "facilities",
      "users",
      "academic_years",
      "timetable_settings",
      "intakes",
      "student_groups",
    ]) {
      await resetSequence(client, table);
    }

    await client.query("COMMIT");

    console.log("Import complete:");
    console.log({
      campuses: campusRows.length,
      colleges: collegeRows.length,
      schools: schoolRows.length,
      programs: programRows.length,
      modules: moduleRows.length,
      skippedModules,
      facilities: facilityRows.length,
      users: userRows.length,
      skippedUsers,
      academicYears: ayRows.length,
      intakes: intakeRows.length,
      studentGroups: groupRows.length,
    });
  } catch (error) {
    await client.query("ROLLBACK");
    console.error("Import failed:", error.message);
    process.exitCode = 1;
  } finally {
    await client.end();
  }
}

main();
