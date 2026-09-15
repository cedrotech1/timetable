import { Op } from "sequelize";
import db from "../database/models/index.js";

const {
  Module,
  Facility,
  Users,
  Program,
  StudentGroup,
  Intake,
  Campus,
  School,
  College,
  Timetable,
  TimetableSession,
  TimetableGroup,
  TimetableLecturer,
  sequelize,
} = db;

function norm(s) {
  return String(s || "")
    .toLowerCase()
    .trim()
    .replace(/\s+/g, " ");
}

function likeScore(a, b) {
  const aa = norm(a);
  const bb = norm(b);
  if (!aa || !bb) return 0;
  if (aa === bb) return 100;
  if (aa.includes(bb) || bb.includes(aa)) return 80;
  // simple token overlap
  const ta = aa.split(" ").filter((t) => t.length >= 3);
  const tb = new Set(bb.split(" "));
  if (!ta.length) return 0;
  let hits = 0;
  for (const t of ta) if (tb.has(t) || bb.includes(t)) hits += 1;
  return Math.round((100 * hits) / ta.length);
}

export function parseGroupSizeHint(text) {
  const t = String(text || "");
  const each = t.match(/=\s*(\d+)\s*each/i);
  if (each) return { mode: "each", size: Number(each[1]) };
  const total = t.match(/=\s*(\d+)/);
  if (total) return { mode: "total", size: Number(total[1]) };
  return { mode: "each", size: 0 };
}

export function parseGroupNumbers(text) {
  const nums = [];
  const t = String(text || "");
  const m = t.match(/group\s*((?:\d+\s*[&,and\s]*)+)/i);
  if (m) {
    const digits = m[1].match(/\d+/g) || [];
    digits.forEach((n) => nums.push(Number(n)));
  }
  const gp = t.matchAll(/\b(?:gp|g)\s*([0-9]+)\b/gi);
  for (const g of gp) nums.push(Number(g[1]));
  return [...new Set(nums.filter((n) => n > 0))];
}

function matchProgram(programs, hint, moduleProgramVotes = {}) {
  let hintN = norm(hint).replace(/hounrs|honors/g, "honours");
  const stop = new Set([
    "bachelor",
    "master",
    "business",
    "administration",
    "with",
    "honours",
    "in",
    "of",
    "the",
    "and",
    "group",
    "year",
    "programme",
    "program",
    "each",
  ]);
  const hintTokens = hintN.split(/\s+/).filter((t) => t.length >= 4 && !stop.has(t));
  const hintBachelor = /\bbachelor\b/.test(hintN) || /\bbba\b/.test(hintN);
  const hintMaster = /\bmaster\b/.test(hintN);
  // Distinctive subject tokens that must not be ignored (Accounting vs Finance-only)
  const distinctive = hintTokens.filter((t) =>
    /account|financ|transport|logist|market|econom|educat|engin|nurs|medic|law|agric|comput|statist|procure|supply/i.test(
      t
    )
  );

  const scored = programs.map((p) => {
    let nameN = norm(p.name)
      .replace(/hounrs|honors/g, "honours")
      .replace(/&/g, " and ");
    let score = likeScore(hintN, nameN);
    let hits = 0;
    for (const t of hintTokens) if (nameN.includes(t)) hits += 1;
    if (hintTokens.length) score += Math.round(70 * (hits / hintTokens.length));

    // Penalize programs that miss distinctive hint words (e.g. "accounting")
    if (distinctive.length) {
      let dHits = 0;
      for (const t of distinctive) if (nameN.includes(t)) dHits += 1;
      score += Math.round(50 * (dHits / distinctive.length));
      const missed = distinctive.filter((t) => !nameN.includes(t));
      // Missing "accounting" while hint has it → strongly prefer ACCOUNTING & FINANCE over FINANCE-only
      score -= missed.length * 45;
    }

    const isMaster = /\bmaster\b/.test(nameN);
    const isBachelor = /\bbachelor\b/.test(nameN) || /\bbba\b/.test(nameN) || /\bbsc\b/.test(nameN);
    if (hintBachelor && isMaster) score -= 50;
    if (hintMaster && isBachelor) score -= 50;
    if (hintBachelor && isBachelor) score += 15;
    if (hintMaster && isMaster) score += 15;

    const pid = Number(p.id);
    if (moduleProgramVotes[pid]) score += Math.min(80, 15 * moduleProgramVotes[pid]);

    return { program: p, score };
  });

  scored.sort((a, b) => b.score - a.score || Number(a.program.id) - Number(b.program.id));
  const best = scored[0] || null;
  if (!best || best.score < 55) return [null, best?.score || 0, scored.slice(0, 8)];
  return [best.program, best.score, scored.slice(0, 8)];
}

function inferProgramVotes(modules, rows, programHint = "") {
  const votes = {};
  const hintN = norm(programHint).replace(/hounrs|honors/g, "honours");
  for (const row of rows || []) {
    const code = String(row.module_code || row.moduleCode || "")
      .toUpperCase()
      .replace(/\s+/g, "");
    if (!code) continue;
    const matches = modules.filter(
      (m) =>
        String(m.code || "")
          .toUpperCase()
          .replace(/\s+/g, "") === code
    );
    if (!matches.length) continue;
    if (matches.length === 1) {
      const pid = Number(matches[0].programId);
      if (pid) votes[pid] = (votes[pid] || 0) + 1;
      continue;
    }
    // Duplicate codes across programs: prefer the program whose name fits the section hint
    let best = null;
    let bestScore = -1;
    for (const m of matches) {
      const pname = norm(m.programName || m.program?.name || "");
      let score = likeScore(hintN, pname);
      if (hintN.includes("account") && pname.includes("account")) score += 40;
      if (hintN.includes("transport") && pname.includes("transport")) score += 40;
      if (hintN.includes("logist") && pname.includes("logist")) score += 40;
      if (score > bestScore) {
        bestScore = score;
        best = m;
      }
    }
    const pid = Number(best?.programId);
    if (pid) votes[pid] = (votes[pid] || 0) + 1;
  }
  return votes;
}

function matchGroups(groups, programId, year, groupNums, campusId = null) {
  const nums = groupNums || [];
  const matched = [];
  for (const g of groups) {
    if (programId && Number(g.programId) !== Number(programId)) continue;
    if (year && Number(g.yearOfStudy) !== Number(year)) continue;
    if (campusId && g.campusId && Number(g.campusId) !== Number(campusId)) continue;
    let gNums = parseGroupNumbers(g.name);
    if (!gNums.length) {
      const m = String(g.name).match(/(?:^|\D)(\d+)(?:\D|$)/);
      if (m) gNums = [Number(m[1])];
    }
    if (!nums.length) continue;
    if (gNums.some((gn) => nums.includes(gn))) matched.push(g);
  }
  return matched;
}

function resolveSectionRowGroups(sectionGroups, sectionGroupNums, timeGroupNums) {
  if (!sectionGroups?.length) return [];
  const ordered = [...sectionGroups].sort((a, b) => {
    const na = parseGroupNumbers(a.name)[0] || 0;
    const nb = parseGroupNumbers(b.name)[0] || 0;
    return na - nb;
  });
  if (!timeGroupNums?.length) return ordered;

  const sectionNums = (sectionGroupNums || []).map(Number);
  const absolute = [];
  for (const t of timeGroupNums) {
    const hit = ordered.find((g) => parseGroupNumbers(g.name).includes(Number(t)));
    if (hit && sectionNums.includes(Number(t))) absolute.push(hit);
  }
  if (absolute.length) return absolute;

  // Relative: GP1 → 1st group in this section
  const resolved = [];
  for (const t of timeGroupNums) {
    const idx = Number(t) - 1;
    if (idx >= 0 && ordered[idx]) resolved.push(ordered[idx]);
  }
  return resolved.length ? resolved : ordered;
}

function matchModule(modules, code, name, programId, year, semester) {
  const codeN = String(code || "")
    .toUpperCase()
    .replace(/\s+/g, "");
  const nameN = norm(name);

  if (codeN) {
    const exact = modules.filter(
      (m) =>
        String(m.code || "")
          .toUpperCase()
          .replace(/\s+/g, "") === codeN
    );
    if (exact.length) {
      exact.sort((a, b) => {
        let sa = 0;
        let sb = 0;
        if (programId && Number(a.programId) === Number(programId)) sa += 20;
        if (programId && Number(b.programId) === Number(programId)) sb += 20;
        if (year && String(a.year) === String(year)) sa += 10;
        if (year && String(b.year) === String(year)) sb += 10;
        if (semester && String(a.semester) === String(semester)) sa += 10;
        if (semester && String(b.semester) === String(semester)) sb += 10;
        return sb - sa;
      });
      return [exact[0], 100, exact];
    }
  }

  let candidates = modules.filter((m) => {
    if (programId && Number(m.programId) !== Number(programId)) return false;
    if (year && String(m.year) !== String(year)) return false;
    if (semester && String(m.semester) !== String(semester)) return false;
    return true;
  });
  if (candidates.length < 3 && programId) {
    candidates = modules.filter((m) => Number(m.programId) === Number(programId));
  }
  if (!codeN && nameN) {
    const exactName = candidates.find((m) => norm(m.name) === nameN);
    if (exactName) return [exactName, 95, candidates];
  }
  return [null, 0, candidates.slice(0, 40)];
}

function matchFacility(facilities, room, capacity = null) {
  const roomT = String(room || "").trim();
  if (!roomT) return [null, 0];
  let best = null;
  let bestScore = 0;
  for (const f of facilities) {
    const hay = `${f.name || ""} ${f.buildName || ""} ${f.name2 || ""} ${f.campus?.name || ""}`;
    let score = Math.max(likeScore(roomT, f.name), likeScore(roomT, f.buildName || ""), likeScore(roomT, hay));
    const tokens = norm(roomT).split(/\s+/).filter((t) => t.length >= 2);
    const hayN = norm(hay);
    let hits = 0;
    for (const t of tokens) if (hayN.includes(t)) hits += 1;
    if (tokens.length) score = Math.max(score, Math.round((100 * hits) / tokens.length));
    if (capacity != null && Number(f.capacity) === Number(capacity)) score += 5;
    if (score > bestScore) {
      bestScore = score;
      best = f;
    }
  }
  if (bestScore < 40) return [null, bestScore];
  return [best, bestScore];
}

function matchLecturers(lecturers, raw) {
  const text = String(raw || "").trim();
  if (!text || /^(from\s+)?language\s*center$/i.test(text) || /^none$/i.test(text)) {
    return [null, [], [], []];
  }

  let parts = text.split(/\s*,\s*(?![^()]*\))|\s+and\s+(?![^()]*\))/i).map((p) => p.trim()).filter(Boolean);
  // Fallback: split on ";" when comma/and split failed
  if (parts.length <= 1) {
    parts = text
      .split(/\s*;\s*/)
      .map((p) => p.trim())
      .filter(Boolean);
  }

  let leader = null;
  const others = [];
  const missed = [];
  const usedIds = new Set();

  for (const part of parts) {
    let clean = part
      .replace(/\([^)]*\)/g, " ")
      .replace(/\b(prof\.?|dr\.?|mr\.?|mrs\.?|ms\.?|eng\.?)\b/gi, " ")
      .replace(/\bmodule\s*leader\b|\bML\b/gi, " ")
      .trim()
      .replace(/[.,;]+$/g, "")
      .trim();
    if (!clean || clean.length < 3) continue;
    if (/^(group|gp|g)\s*\d+/i.test(clean)) continue;

    let best = null;
    let bestScore = 0;
    const cleanTokens = norm(clean)
      .split(" ")
      .filter((t) => t.length > 1);

    for (const u of lecturers) {
      if (usedIds.has(u.id)) continue;
      let score = Math.max(
        likeScore(clean, u.names),
        likeScore(clean, u.urEmail || ""),
        likeScore(clean, u.email || "")
      );
      const nameTokens = norm(u.names || "")
        .split(" ")
        .filter((t) => t.length > 1);
      const overlap = cleanTokens.filter((t) => nameTokens.includes(t)).length;
      if (overlap >= 2) score = Math.max(score, 92);
      else if (overlap === 1 && cleanTokens.length <= 3) score = Math.max(score, 72);
      // Prefer surname-ish last token match
      const last = cleanTokens[cleanTokens.length - 1];
      if (last && nameTokens.includes(last) && last.length >= 4) score = Math.max(score, 68);
      if (score > bestScore) {
        bestScore = score;
        best = u;
      }
    }

    if (!best || bestScore < 55) {
      missed.push(part.trim());
      continue;
    }
    usedIds.add(best.id);
    const obj = { id: best.id, names: best.names, urEmail: best.urEmail, email: best.email };
    if (!leader) leader = obj;
    else others.push(obj);
  }

  return [leader, others, [], missed];
}

function groupDto(g) {
  return {
    id: Number(g.id),
    name: g.name,
    size: Number(g.size || 0),
    programId: Number(g.programId),
    programName: g.programName,
    yearOfStudy: Number(g.yearOfStudy),
    campusId: Number(g.campusId || 0),
    campusName: g.campusName || "",
    intakeId: Number(g.intakeId || 0),
  };
}

/**
 * Match parsed Excel sections against live DB (programs, year, intakes/groups, modules, rooms).
 */
export async function matchImportSections({ sections, campusId = null, semester = null, facilityMode = "excel" }) {
  const [modulesRaw, facilities, lecturers, programs, groupRows] = await Promise.all([
    Module.findAll({ include: [{ model: Program, as: "program", attributes: ["id", "name"] }] }),
    Facility.findAll({ include: [{ model: Campus, as: "campus" }] }),
    Users.findAll({
      where: { deleted: { [Op.ne]: "yes" }, active: true },
      attributes: { exclude: ["password", "resetcode"] },
      limit: 5000,
    }),
    Program.findAll(),
    StudentGroup.findAll({
      include: [
        {
          model: Intake,
          as: "intake",
          include: [
            { model: Campus, as: "campus" },
            { model: Program, as: "program" },
          ],
        },
      ],
    }),
  ]);

  const modules = modulesRaw.map((m) => {
    const j = m.toJSON();
    return { ...j, programName: j.program?.name || "" };
  });

  const groups = groupRows.map((g) => {
    const j = g.toJSON();
    return {
      id: j.id,
      name: j.name,
      size: j.size,
      intakeId: j.intakeId,
      programId: j.intake?.programId,
      yearOfStudy: j.intake?.yearOfStudy,
      campusId: j.intake?.campusId,
      programName: j.intake?.program?.name,
      campusName: j.intake?.campus?.name,
    };
  });

  const defaultCampusId = campusId ? Number(campusId) : null;
  const outSections = [];

  for (let secIndex = 0; secIndex < (sections || []).length; secIndex += 1) {
    const sec = sections[secIndex];
    const title = sec.title || "";
    const year = Number(sec.year || 0);
    const programHint = sec.program_hint || sec.programHint || title;
    const groupHint = sec.group_hint || sec.groupHint || title;
    const groupNums = parseGroupNumbers(`${groupHint} ${title}`);
    const sizeHint = parseGroupSizeHint(`${title} ${groupHint}`);

    const moduleVotes = inferProgramVotes(modules, sec.rows || [], programHint);
    let program = null;
    let pScore = 0;
    let programCandidates = [];

    if (sec.forced_program_id || sec.forcedProgramId) {
      const forcedId = Number(sec.forced_program_id || sec.forcedProgramId);
      program = programs.find((p) => Number(p.id) === forcedId) || null;
      pScore = program ? 100 : 0;
    } else {
      [program, pScore, programCandidates] = matchProgram(programs, programHint, moduleVotes);
    }

    const programId = program?.id || null;
    const sectionCampusId = Number(sec.campus_id || sec.campusId || defaultCampusId || 0) || null;

    let sectionGroups = matchGroups(groups, programId, year || null, groupNums, sectionCampusId);
    if (!sectionGroups.length && sectionCampusId) {
      sectionGroups = matchGroups(groups, programId, year || null, groupNums, null);
    }

    const matchedGroupNums = new Set();
    for (const g of sectionGroups) {
      parseGroupNumbers(g.name).forEach((n) => matchedGroupNums.add(n));
      const m = String(g.name).match(/(?:^|\D)(\d+)(?:\D|$)/);
      if (m) matchedGroupNums.add(Number(m[1]));
    }
    const missingGroupNums = groupNums.filter((n) => !matchedGroupNums.has(Number(n)));
    const needsGroups = !programId || missingGroupNums.length > 0;

    const matchedRows = [];
    let okCount = 0;
    let warnCount = 0;
    let errCount = 0;

    for (let rowIndex = 0; rowIndex < (sec.rows || []).length; rowIndex += 1) {
      const row = sec.rows[rowIndex];
      const errors = [];
      const warnings = [];

      const day = String(row.day || "").trim();
      const start = String(row.start || "").trim();
      const end = String(row.end || "").trim();
      const moduleCode = String(row.module_code || row.moduleCode || "").trim();
      const moduleName = String(row.module_name || row.moduleName || "").trim();
      const lecturersRaw = String(row.lecturers || "").trim();
      const classroom = String(row.classroom || "").trim();
      const capacity = row.room_capacity ?? row.roomCapacity ?? null;
      const timeGroupNums = (row.time_group_nums || row.timeGroupNums || []).map(Number);

      if (!day) errors.push("Missing day");
      if (!start || !end) errors.push("Missing time");
      if (!moduleCode && !moduleName) warnings.push("No module in Excel — pick a system module");

      const [mod, , moduleCandidates] = matchModule(
        modules,
        moduleCode,
        moduleName,
        programId,
        year || null,
        semester
      );
      if (!mod) warnings.push(`Module code not in system: ${moduleCode || moduleName}`);

      const [fac, fScore] =
        facilityMode === "auto"
          ? [null, 0]
          : matchFacility(facilities, classroom, capacity);
      if (facilityMode === "auto") {
        warnings.push("Excel classroom ignored — free facility will be auto-assigned");
      } else if (classroom && !fac) {
        warnings.push(`Facility not matched: ${classroom}`);
      } else if (!classroom) {
        warnings.push("No classroom in Excel — pick a facility or use Auto mode");
      } else if (fScore < 70) {
        warnings.push(`Facility weak match (${fScore}%): ${fac.name}`);
      }

      const [leader, others, , missed] = matchLecturers(lecturers, lecturersRaw);

      let rowGroups = resolveSectionRowGroups(sectionGroups, groupNums, timeGroupNums);
      if (!rowGroups.length) warnings.push("No groups matched — create intake/groups first");

      const status = errors.length ? "error" : warnings.length ? "warning" : "ok";
      if (status === "ok") okCount += 1;
      else if (status === "warning") {
        okCount += 1;
        warnCount += 1;
      } else errCount += 1;

      matchedRows.push({
        rowIndex,
        status,
        errors,
        warnings,
        day,
        start,
        end,
        excel: {
          moduleCode,
          moduleName,
          lecturers: lecturersRaw,
          classroom,
          timeRaw: row.time_raw || row.timeRaw || "",
        },
        module: mod
          ? {
              id: mod.id,
              code: mod.code,
              name: mod.name,
              year: mod.year,
              semester: mod.semester,
              programId: mod.programId,
            }
          : null,
        moduleCandidates: (moduleCandidates || []).slice(0, 40).map((m) => ({
          id: m.id,
          code: m.code,
          name: m.name,
          year: m.year,
          semester: m.semester,
          programId: m.programId,
        })),
        facility: fac
          ? {
              id: fac.id,
              name: fac.name,
              capacity: fac.capacity,
              buildName: fac.buildName,
              campus: fac.campus?.name,
            }
          : null,
        lecturers: { leader, others },
        missedLecturers: missed || [],
        students: row.students != null ? Number(row.students) : null,
        excelRoomCapacity: capacity != null ? Number(capacity) : null,
        timeGroupNums,
        groups: rowGroups.map(groupDto),
      });
    }

    outSections.push({
      sectionIndex: secIndex,
      title,
      sheet: sec.sheet || "",
      year,
      groupNumbers: groupNums,
      missingGroupNumbers: missingGroupNums,
      sizeHint,
      needsGroups,
      program: program
        ? { id: program.id, name: program.name, code: program.code, matchScore: pScore }
        : null,
      programCandidates: (programCandidates || []).map((c) => ({
        id: c.program.id,
        name: c.program.name,
        code: c.program.code,
        score: c.score,
      })),
      groups: sectionGroups.map(groupDto),
      stats: {
        rows: matchedRows.length,
        ok: okCount,
        warnings: warnCount,
        errors: errCount,
      },
      rows: matchedRows,
    });
  }

  return { sections: outSections };
}

function normSlotTime(t) {
  const s = String(t || "").trim();
  if (/^\d{1,2}:\d{2}$/.test(s)) {
    const [h, m] = s.split(":");
    return `${h.padStart(2, "0")}:${m}:00`;
  }
  if (/^\d{1,2}:\d{2}:\d{2}$/.test(s)) {
    const [h, m, sec] = s.split(":");
    return `${h.padStart(2, "0")}:${m}:${sec}`;
  }
  return s;
}

function timesOverlapStr(aStart, aEnd, bStart, bEnd) {
  const aS = String(aStart).slice(0, 8);
  const aE = String(aEnd).slice(0, 8);
  const bS = String(bStart).slice(0, 8);
  const bE = String(bEnd).slice(0, 8);
  return aS < bE && aE > bS;
}

function rowRequiredStudents(sec, row) {
  if (row.students && Number(row.students) > 0) return Number(row.students);
  const groups = row.groups?.length ? row.groups : sec.groups || [];
  const sum = groups.reduce((s, g) => s + (Number(g.size) || 0), 0);
  if (sum > 0) return sum;
  if (row.excelRoomCapacity || row.excel_room_capacity) {
    return Number(row.excelRoomCapacity || row.excel_room_capacity);
  }
  return 40;
}

/**
 * Auto-assign free rooms across the whole import:
 * - Considers all campus facilities + already-saved timetable bookings
 * - Avoids ROOM clashes within this Excel batch
 * - Detects GROUP time duplicates (same group twice at same slot) before save
 * - Reuses a room when same module + overlapping groups share the slot (combined class)
 * - Picks smallest room that fits student count (resource optimization)
 */
export async function autoAssignImportFacilities({
  sections,
  academicYearId,
  semester,
  campusId = null,
}) {
  const { getAvailableFacilities } = await import("./timetableService.js");
  const ay = Number(academicYearId);
  const sem = String(semester);
  const out = (sections || []).map((sec) => ({
    ...sec,
    rows: (sec.rows || []).map((r) => ({ ...r })),
  }));

  /** @type {{ facilityId:number, day:string, start:string, end:string, moduleId:number, groupIds:number[], section:number, rowIndex:number, facilityName?:string }[]} */
  const booked = [];

  const pushBooked = (row, si, ri, facility) => {
    const day = String(row.day || "").trim();
    const start = normSlotTime(row.start);
    const end = normSlotTime(row.end);
    const groupIds = (row.groups || []).map((g) => Number(g.id)).filter(Boolean);
    booked.push({
      facilityId: Number(facility.id),
      facilityName: facility.name || null,
      day,
      start,
      end,
      moduleId: Number(row.module?.id) || 0,
      groupIds,
      section: si,
      rowIndex: ri,
    });
  };

  // Seed locked / user-picked facilities
  out.forEach((sec, si) => {
    (sec.rows || []).forEach((row, ri) => {
      if (row.facilityUserPicked && row.facility?.id && row.day && row.start && row.end) {
        pushBooked(row, si, ri, row.facility);
      }
    });
  });

  const jobs = [];
  out.forEach((sec, si) => {
    (sec.rows || []).forEach((row, ri) => {
      if (row.facilityUserPicked) return;
      row.facility = null;
      jobs.push({
        si,
        ri,
        sec,
        row,
        need: rowRequiredStudents(sec, row),
      });
    });
  });
  // Largest classes first → claim suitable rooms early (better packing)
  jobs.sort((a, b) => b.need - a.need || a.si - b.si || a.ri - b.ri);

  let assigned = 0;
  let failed = 0;
  let reused = 0;
  let groupBlocked = 0;

  for (const job of jobs) {
    const { si, ri, row, need } = job;
    const day = String(row.day || "").trim();
    const start = normSlotTime(row.start);
    const end = normSlotTime(row.end);
    row.day = day;
    row.start = start.slice(0, 5);
    row.end = end.slice(0, 5);

    if (!day || !start || !end || start >= end) {
      row.warnings = [...(row.warnings || []).filter((w) => !/auto-assign|free facility|classroom|GROUP|ROOM/i.test(w))];
      row.warnings.push("Missing/invalid day/time — cannot auto-assign facility");
      row.status = "error";
      failed += 1;
      continue;
    }

    const modId = Number(row.module?.id) || 0;
    const groupIds = (row.groups || []).map((g) => Number(g.id)).filter(Boolean);
    const groupNames = (row.groups || []).map((g) => g.name).filter(Boolean).join(", ") || "group(s)";

    // GROUP slot already taken in this import?
    const groupHit = booked.find(
      (b) =>
        String(b.day) === day &&
        timesOverlapStr(b.start, b.end, start, end) &&
        groupIds.some((gid) => (b.groupIds || []).includes(gid))
    );

    if (groupHit) {
      const sameModule = modId && Number(groupHit.moduleId) === modId;
      const hitGroups = new Set((groupHit.groupIds || []).map(Number));
      const rowGroups = new Set(groupIds);
      const sameGroupSet =
        hitGroups.size > 0 &&
        rowGroups.size > 0 &&
        hitGroups.size === rowGroups.size &&
        [...rowGroups].every((g) => hitGroups.has(g));

      // Exact duplicate groups at this slot → always a GROUP CONFLICT (not a room issue)
      if (sameGroupSet) {
        row.facility = null;
        row.warnings = [
          ...(row.warnings || []).filter((w) => !/auto-assign|free facility|classroom|No free|GROUP|ROOM/i.test(w)),
        ];
        row.warnings.push(
          `GROUP CONFLICT (duplicate Excel row): ${groupNames} already have a row at ${day} ${row.start}–${row.end}` +
            (groupHit.facilityName ? ` in “${groupHit.facilityName}”` : "") +
            `. Delete this duplicate (or change time/group). Auto-assign will not pick another room.`
        );
        row.status = "error";
        row.conflictKind = "group";
        groupBlocked += 1;
        failed += 1;
        continue;
      }

      // Different groups, same module → share one room (combined class)
      if (sameModule && groupHit.facilityId) {
        const fac = {
          id: groupHit.facilityId,
          name: groupHit.facilityName || `Facility #${groupHit.facilityId}`,
          capacity: null,
          buildName: null,
          campus: null,
        };
        try {
          const freeProbe = await getAvailableFacilities({
            academicYearId: ay,
            semester: sem,
            sessions: [{ day, start: row.start, end: row.end }],
            minCapacity: 0,
          });
          const live = freeProbe.find((f) => Number(f.id) === Number(groupHit.facilityId));
          row.facility = live
            ? {
                id: live.id,
                name: live.name,
                capacity: live.capacity,
                buildName: live.buildName,
                campus: live.campus?.name,
              }
            : fac;
        } catch {
          row.facility = fac;
        }
        row.facilityUserPicked = false;
        row.warnings = [
          ...(row.warnings || []).filter((w) => !/auto-assign|free facility|classroom|No free|GROUP|ROOM|Shared/i.test(w)),
        ];
        row.warnings.push(
          `ROOM shared (combined class): same module at ${day} ${row.start}–${row.end} — reused “${row.facility.name}”. Prefer one plan with all groups.`
        );
        row.status = "warning";
        pushBooked(row, si, ri, row.facility);
        reused += 1;
        assigned += 1;
        continue;
      }

      row.facility = null;
      row.warnings = [...(row.warnings || []).filter((w) => !/auto-assign|free facility|classroom|No free|GROUP|ROOM/i.test(w))];
      row.warnings.push(
        `GROUP CONFLICT: ${groupNames} already busy at ${day} ${row.start}–${row.end} in this import` +
          (groupHit.facilityName ? ` (room “${groupHit.facilityName}”)` : "") +
          `. Change the time/group or remove the other row.`
      );
      row.status = "error";
      row.conflictKind = "group";
      groupBlocked += 1;
      failed += 1;
      continue;
    }

    let free = [];
    try {
      free = await getAvailableFacilities({
        academicYearId: ay,
        semester: sem,
        sessions: [{ day, start: row.start, end: row.end }],
        minCapacity: need,
      });
    } catch {
      free = [];
    }

    if (campusId) {
      const cid = Number(campusId);
      const filtered = free.filter((f) => Number(f.campusId) === cid || Number(f.campus?.id) === cid);
      if (filtered.length) free = filtered;
    }

    // Exclude rooms already claimed in this import at overlapping times
    free = free
      .filter((f) => {
        return !booked.some(
          (b) =>
            Number(b.facilityId) === Number(f.id) &&
            String(b.day) === day &&
            timesOverlapStr(b.start, b.end, start, end)
        );
      })
      .sort((a, b) => (Number(a.capacity) || 99999) - (Number(b.capacity) || 99999));

    const pick = free[0];
    if (!pick) {
      row.facility = null;
      row.warnings = [...(row.warnings || []).filter((w) => !/auto-assign|free facility|classroom|No free|Shared room|GROUP|ROOM/i.test(w))];
      row.warnings.push(
        `ROOM CONFLICT / no free facility: need ≥${need} seats at ${day} ${row.start}–${row.end} (checked all available campus rooms against saved timetable + this import)`
      );
      row.status = row.status === "error" ? "error" : "warning";
      row.conflictKind = "facility";
      failed += 1;
      continue;
    }

    row.facility = {
      id: pick.id,
      name: pick.name,
      capacity: pick.capacity,
      buildName: pick.buildName,
      campus: pick.campus?.name,
    };
    row.facilityUserPicked = false;
    row.warnings = [
      ...(row.warnings || []).filter((w) => !/auto-assign|free facility|classroom|No free|weak match|not matched|Shared room|GROUP|ROOM/i.test(w)),
    ];
    row.warnings.push(
      `Auto room: “${pick.name}”` +
        (pick.capacity != null ? ` (${pick.capacity} seats)` : "") +
        (pick.buildName ? ` · ${pick.buildName}` : "") +
        ` ≥${need} students`
    );
    if (row.status === "error" && row.module?.id && (row.groups || []).length) row.status = "warning";
    pushBooked(row, si, ri, row.facility);
    assigned += 1;
  }

  return {
    sections: out,
    stats: { assigned, failed, reused, groupBlocked, shared: reused, jobs: jobs.length },
  };
}

/**
 * Facility weekly calendar — all facilities (empty or booked), with rich slot details.
 */
export async function getFacilityCalendar({
  facilityId = null,
  academicYearId,
  semester,
  campusId = null,
}) {
  const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
  const where = {
    academicYearId: Number(academicYearId),
    semester: String(semester),
  };
  if (facilityId) where.facilityId = Number(facilityId);

  const facilityWhere = {};
  if (facilityId) facilityWhere.id = Number(facilityId);
  if (campusId) facilityWhere.campusId = Number(campusId);

  const [allFacilities, plans] = await Promise.all([
    Facility.findAll({
      where: facilityWhere,
      include: [{ model: Campus, as: "campus" }],
      order: [["name", "ASC"]],
    }),
    Timetable.findAll({
      where,
      include: [
        { model: Module, as: "module", include: [{ model: Program, as: "program" }] },
        {
          model: Facility,
          as: "facility",
          include: [{ model: Campus, as: "campus" }],
          ...(campusId
            ? { where: { campusId: Number(campusId) }, required: true }
            : {}),
        },
        {
          model: Users,
          as: "leader",
          attributes: { exclude: ["password", "resetcode"] },
          required: false,
        },
        { model: TimetableSession, as: "sessions", required: true },
        {
          model: TimetableGroup,
          as: "timetableGroups",
          include: [
            {
              model: StudentGroup,
              as: "group",
              include: [
                {
                  model: Intake,
                  as: "intake",
                  include: [
                    { model: Campus, as: "campus" },
                    {
                      model: Program,
                      as: "program",
                      include: [{ model: School, as: "school", include: [{ model: College, as: "college" }] }],
                    },
                  ],
                },
              ],
            },
          ],
        },
        {
          model: TimetableLecturer,
          as: "timetableLecturers",
          required: false,
          include: [
            {
              model: Users,
              as: "lecturer",
              attributes: { exclude: ["password", "resetcode"] },
              required: false,
            },
          ],
        },
      ],
      order: [["id", "ASC"]],
    }),
  ]);

  const emptyBlock = (fac) => ({
    facility: {
      id: fac.id,
      name: fac.name,
      capacity: fac.capacity,
      type: fac.type,
      buildName: fac.buildName,
      buildCode: fac.buildCode,
      site: fac.site,
      campus: fac.campus
        ? { id: fac.campus.id, name: fac.campus.name }
        : null,
    },
    sessions: [],
    byDay: Object.fromEntries(days.map((d) => [d, []])),
    stats: { sessions: 0, daysUsed: 0 },
  });

  const byFacility = {};
  for (const fac of allFacilities) {
    byFacility[fac.id] = emptyBlock(fac.toJSON ? fac.toJSON() : fac);
  }

  for (const plan of plans) {
    const j = plan.toJSON();
    const fid = j.facilityId;
    if (!byFacility[fid]) {
      byFacility[fid] = emptyBlock(
        j.facility || { id: fid, name: `Facility #${fid}`, campus: null }
      );
    }

    const groups = (j.timetableGroups || [])
      .map((tg) => tg.group)
      .filter(Boolean)
      .map((g) => ({
        id: g.id,
        name: g.name,
        size: g.size,
        program: g.intake?.program?.name,
        programCode: g.intake?.program?.code,
        school: g.intake?.program?.school?.name,
        college: g.intake?.program?.school?.college?.name,
        campus: g.intake?.campus?.name,
        yearOfStudy: g.intake?.yearOfStudy,
        intakeId: g.intake?.id,
      }));

    const otherLecturers = (j.timetableLecturers || [])
      .map((tl) => tl.lecturer)
      .filter(Boolean)
      .map((u) => ({ id: u.id, names: u.names, urEmail: u.urEmail, email: u.email }));

    for (const sess of j.sessions || []) {
      const entry = {
        timetableId: j.id,
        day: sess.day,
        startTime: sess.startTime,
        endTime: sess.endTime,
        status: j.status,
        moduleId: j.module?.id,
        moduleName: j.module?.name,
        moduleCode: j.module?.code,
        moduleCredits: j.module?.credits,
        moduleYear: j.module?.year,
        moduleSemester: j.module?.semester,
        programName: j.module?.program?.name,
        programCode: j.module?.program?.code,
        facilityId: fid,
        facilityName: byFacility[fid].facility.name,
        facilityCapacity: byFacility[fid].facility.capacity,
        facilityType: byFacility[fid].facility.type,
        facilityCampus: byFacility[fid].facility.campus?.name,
        leader: j.leader
          ? {
              id: j.leader.id,
              names: j.leader.names,
              urEmail: j.leader.urEmail,
              email: j.leader.email,
              phone: j.leader.phone,
            }
          : null,
        otherLecturers,
        groups,
        groupsLabel: groups.map((g) => g.name).filter(Boolean).join(", "),
      };
      byFacility[fid].sessions.push(entry);
      if (byFacility[fid].byDay[sess.day]) byFacility[fid].byDay[sess.day].push(entry);
    }
  }

  for (const f of Object.values(byFacility)) {
    for (const d of days) {
      f.byDay[d].sort((a, b) => String(a.startTime).localeCompare(String(b.startTime)));
    }
    f.stats = {
      sessions: f.sessions.length,
      daysUsed: days.filter((d) => f.byDay[d].length > 0).length,
    };
  }

  const list = Object.values(byFacility).sort((a, b) =>
    String(a.facility.name || "").localeCompare(String(b.facility.name || ""))
  );

  return {
    days,
    facilities: list,
    facilityId: facilityId ? Number(facilityId) : null,
    timeSlots: ["08:00", "09:00", "10:00", "11:00", "12:00", "13:00", "14:00", "15:00", "16:00", "17:00"],
  };
}

/**
 * Create/ensure intake + Group N rows (PHP import_create_intake).
 * Optional groupSizes: { 1: 130, 2: 130 } overrides sizeEach/sizeMode split.
 */
export async function createIntakeGroups({
  programId,
  yearOfStudy,
  campusId,
  groupNumbers,
  sizeEach,
  sizeMode = "each",
  groupSizes = null,
}) {
  const pid = Number(programId);
  const yos = Number(yearOfStudy);
  const cid = Number(campusId);
  const nums = [...new Set((groupNumbers || []).map(Number).filter((n) => n > 0))].sort((a, b) => a - b);
  let size = Number(sizeEach) || 0;
  const mode = sizeMode === "total" ? "total" : "each";

  if (!pid) throw Object.assign(new Error("Select a program."), { code: "VALIDATION" });
  if (yos < 1) throw Object.assign(new Error("Year of study is required."), { code: "VALIDATION" });
  if (!cid) throw Object.assign(new Error("Select a campus."), { code: "VALIDATION" });
  if (!nums.length) throw Object.assign(new Error("Provide at least one group number."), { code: "VALIDATION" });
  if (size < 1 && !groupSizes) size = 40;

  const program = await Program.findByPk(pid);
  const campus = await Campus.findByPk(cid);
  if (!program) throw Object.assign(new Error("Program not found."), { code: "NOT_FOUND" });
  if (!campus) throw Object.assign(new Error("Campus not found."), { code: "NOT_FOUND" });

  const sizes = {};
  if (groupSizes && typeof groupSizes === "object") {
    for (const num of nums) {
      sizes[num] = Number(groupSizes[num] || groupSizes[String(num)] || size || 40);
    }
  } else if (mode === "total") {
    const base = Math.floor(size / nums.length);
    const rem = size % nums.length;
    nums.forEach((n, i) => {
      sizes[n] = base + (i < rem ? 1 : 0);
    });
  } else {
    nums.forEach((n) => {
      sizes[n] = size;
    });
  }

  return sequelize.transaction(async (t) => {
    let intake = await Intake.findOne({
      where: { programId: pid, yearOfStudy: yos, campusId: cid },
      transaction: t,
    });
    let intakeCreated = false;
    if (!intake) {
      intake = await Intake.create(
        {
          programId: pid,
          campusId: cid,
          yearOfStudy: yos,
          size: 0,
          year: 0,
          month: 0,
        },
        { transaction: t }
      );
      intakeCreated = true;
    }

    const created = [];
    const existing = [];
    for (const num of nums) {
      const name = `Group ${num}`;
      const gsize = sizes[num];
      // Also accept legacy "Group1" / "group 1" naming
      let group = await StudentGroup.findOne({
        where: { intakeId: intake.id, name },
        transaction: t,
      });
      if (!group) {
        const siblings = await StudentGroup.findAll({ where: { intakeId: intake.id }, transaction: t });
        group =
          siblings.find((g) => {
            const gNums = parseGroupNumbers(g.name);
            if (gNums.includes(num)) return true;
            const m = String(g.name).match(/(?:^|\D)(\d+)(?:\D|$)/);
            return m && Number(m[1]) === num;
          }) || null;
      }
      if (group) {
        await group.update({ size: gsize, name }, { transaction: t });
        existing.push(group);
      } else {
        group = await StudentGroup.create(
          { intakeId: intake.id, name, size: gsize },
          { transaction: t }
        );
        created.push(group);
      }
    }

    const allGroups = await StudentGroup.findAll({ where: { intakeId: intake.id }, transaction: t });
    const intakeSize = allGroups.reduce((s, g) => s + (Number(g.size) || 0), 0);
    await intake.update({ size: intakeSize, year: 0, month: 0 }, { transaction: t });

    return {
      intakeId: intake.id,
      intakeCreated,
      program: { id: program.id, name: program.name },
      campus: { id: campus.id, name: campus.name },
      yearOfStudy: yos,
      intakeSize,
      groups: [...existing, ...created].map((g) => ({
        id: g.id,
        name: g.name,
        size: g.size,
        intakeId: intake.id,
      })),
      createdCount: created.length,
      existingCount: existing.length,
      label: `Year ${yos} - ${campus.name}`,
    };
  });
}

function computeGroupSizes(groupNumbers, sizeEach, sizeMode) {
  const nums = [...new Set((groupNumbers || []).map(Number).filter((n) => n > 0))];
  const size = Number(sizeEach) > 0 ? Number(sizeEach) : 40;
  const mode = sizeMode === "total" ? "total" : "each";
  const sizes = {};
  if (mode === "total") {
    const base = Math.floor(size / Math.max(nums.length, 1));
    const rem = size % Math.max(nums.length, 1);
    nums.forEach((n, i) => {
      sizes[n] = base + (i < rem ? 1 : 0);
    });
  } else {
    nums.forEach((n) => {
      sizes[n] = size;
    });
  }
  return sizes;
}

/**
 * Merge all Excel sections into one create-per promotion (program+year+campus),
 * keeping per-group sizes from each section banner (= 260 total / = 109 EACH).
 */
export async function createIntakeGroupsBulk({ campusId, items }) {
  const defaultCampus = Number(campusId) || 0;
  const merged = {};
  for (const item of items || []) {
    const pid = Number(item.programId || item.program_id);
    const yos = Number(item.yearOfStudy || item.year_of_study);
    const cid = Number(item.campusId || item.campus_id || defaultCampus);
    if (!pid || !yos || !cid) continue;
    const key = `${pid}:${yos}:${cid}`;
    const nums = (item.groupNumbers || item.group_numbers || []).map(Number).filter((n) => n > 0);
    if (!nums.length) continue;

    if (!merged[key]) {
      merged[key] = {
        programId: pid,
        yearOfStudy: yos,
        campusId: cid,
        groupNumbers: [],
        groupSizes: {},
      };
    }
    const sectionSizes =
      item.groupSizes ||
      computeGroupSizes(
        nums,
        item.sizeEach || item.size_each || 40,
        item.sizeMode || item.size_mode || "each"
      );
    for (const n of nums) {
      merged[key].groupNumbers.push(n);
      if (sectionSizes[n] != null) merged[key].groupSizes[n] = sectionSizes[n];
    }
  }

  const results = [];
  let totalCreated = 0;
  let totalExisting = 0;
  for (const item of Object.values(merged)) {
    item.groupNumbers = [...new Set(item.groupNumbers)].sort((a, b) => a - b);
    if (!item.groupNumbers.length) continue;
    const r = await createIntakeGroups(item);
    totalCreated += r.createdCount;
    totalExisting += r.existingCount;
    results.push(r);
  }

  return {
    createdCount: totalCreated,
    existingCount: totalExisting,
    promotionCount: results.length,
    results,
  };
}
