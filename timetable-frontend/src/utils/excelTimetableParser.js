/**
 * Parse UR-style timetable Excel (sample.xls) into Year/Group sections + rows.
 * Mirrors PHP Dashboard/timetable_import_excel.php parsers.
 */

import { parseTimeRange } from "./timeFormat.js";

function normalizeDayName(d) {
  const s = String(d || "")
    .toLowerCase()
    .replace(/\s+/g, "")
    .trim();
  const map = {
    monday: "Monday",
    mon: "Monday",
    tuesday: "Tuesday",
    tue: "Tuesday",
    tues: "Tuesday",
    wednesday: "Wednesday",
    wed: "Wednesday",
    wedsday: "Wednesday",
    weds: "Wednesday",
    wednesady: "Wednesday", // common Excel typo
    wendesday: "Wednesday",
    thursday: "Thursday",
    thu: "Thursday",
    thur: "Thursday",
    thurs: "Thursday",
    friday: "Friday",
    fri: "Friday",
    saturday: "Saturday",
    sat: "Saturday",
    sunday: "Sunday",
    sun: "Sunday",
  };
  return map[s] || (d ? String(d).trim() : "");
}

export function parseExcelTime(raw) {
  const text = String(raw || "")
    .replace(/\u00a0/g, " ")
    .replace(/\s+/g, " ")
    .trim();
  const timeGroupNums = [];
  const gp = text.match(/\(?\s*(?:GP|G|Group)\s*([0-9]+(?:\s*[&,]\s*[0-9]+)*)\s*\)?/i);
  if (gp) (gp[1].match(/\d+/g) || []).forEach((n) => timeGroupNums.push(parseInt(n, 10)));

  const { start, end } = parseTimeRange(text);
  return { start, end, time_raw: text, time_group_nums: timeGroupNums };
}

function normalizeHeader(cell) {
  return String(cell || "")
    .toLowerCase()
    .replace(/\u00a0/g, " ")
    .replace(/[\n\r]+/g, " ")
    .replace(/\s*\/\s*/g, "/") // Module / Course → module/course
    .replace(/\s*-\s*/g, "-")
    .replace(/\s+/g, " ")
    .trim();
}

/** Classify a header cell: module_code | module_name | null */
function classifyModuleHeader(raw) {
  const t = normalizeHeader(raw);
  if (!t) return null;

  // CODE first — never treat "Module/Course Name" as code
  if (
    t === "code" ||
    t === "module code" ||
    t === "modulecode" ||
    t === "mod code" ||
    t === "mod.code" ||
    t === "course code" ||
    t === "coursecode" ||
    /^module\s*code\b/.test(t) ||
    /^course\s*code\b/.test(t) ||
    (t.includes("module") && t.includes("code") && !t.includes("name")) ||
    (t.includes("course") && t.includes("code") && !t.includes("name"))
  ) {
    return "module_code";
  }

  // NAME — Module, Module/Course Name, Module Name, Course Name, …
  if (
    t === "module" ||
    t === "modules" ||
    t === "module name" ||
    t === "modulename" ||
    t === "module/course" ||
    t === "module/course name" ||
    t === "module/coursename" ||
    t === "module-course" ||
    t === "module-course name" ||
    t === "course" ||
    t === "course name" ||
    t === "coursename" ||
    t === "subject" ||
    t === "subject name" ||
    t.startsWith("module/course") ||
    t.startsWith("module-course") ||
    (t.includes("module") && t.includes("name")) ||
    (t.includes("course") && t.includes("name") && !t.includes("code")) ||
    (t.includes("module") && t.includes("course") && !t.includes("code"))
  ) {
    return "module_name";
  }

  return null;
}

function isHeaderRow(cells) {
  const parts = cells.map((c) => normalizeHeader(c));
  const joined = parts.join(" | ");
  const hasDay = parts.some((t) => t === "day" || t.startsWith("day "));
  const hasModule = parts.some((t) => classifyModuleHeader(t) != null);
  const hasTime = parts.some((t) => t === "time" || t.includes("time"));
  return hasDay && hasModule && (hasTime || joined.includes("lecturer") || joined.includes("classroom"));
}

function findColMap(cells) {
  const map = {};
  cells.forEach((c, i) => {
    const t = normalizeHeader(c);
    if (t === "day" || /^day\b/.test(t)) {
      map.day = i;
      return;
    }
    if (t === "time" || /^time\b/.test(t)) {
      map.time = i;
      return;
    }

    const modKind = classifyModuleHeader(t);
    if (modKind === "module_code" && map.module_code == null) {
      map.module_code = i;
      return;
    }
    if (modKind === "module_name" && map.module_name == null) {
      map.module_name = i;
      return;
    }

    if (
      t === "credits" ||
      t === "credit" ||
      t === "cr" ||
      t === "no of credits" ||
      t === "number of credits" ||
      (t.includes("credit") && !t.includes("accredited"))
    ) {
      if (map.credits == null) map.credits = i;
      return;
    }
    if (/^lecturer/.test(t) && !t.includes("no of") && !t.includes("number")) {
      map.lecturers = i;
      return;
    }
    if (t.includes("room capacity") || (t.includes("capacity") && !t.includes("student"))) {
      map.room_capacity = i;
      return;
    }
    if (t.includes("class room") || t.includes("classroom") || t === "room" || t === "venue") {
      map.classroom = i;
      return;
    }
    if (t.includes("number of students") || t.includes("no of students") || t === "students") {
      map.students = i;
    }
  });
  if (map.lecturers == null) {
    cells.forEach((c, i) => {
      const t = normalizeHeader(c);
      if (t.includes("lecturer") && !t.includes("no of") && !t.includes("number of lect")) map.lecturers = i;
    });
  }

  // Merged / blank headers: "Module Code" | (empty) → next col is often the course name
  if (map.module_code != null && map.module_name == null) {
    const next = map.module_code + 1;
    const taken = new Set(
      [map.day, map.time, map.module_code, map.lecturers, map.classroom, map.credits, map.students, map.room_capacity].filter(
        (x) => x != null
      )
    );
    if (!taken.has(next) && next < cells.length) {
      const nextH = normalizeHeader(cells[next]);
      // empty sub-header, or explicit name-ish label
      if (
        !nextH ||
        nextH === "name" ||
        nextH === "title" ||
        nextH === "course" ||
        classifyModuleHeader(cells[next]) === "module_name"
      ) {
        map.module_name = next;
      }
    }
  }

  return map;
}

function looksLikeModuleCode(s) {
  const t = String(s || "").trim();
  if (!t || t.length > 40) return false;
  return /^[A-Z]{1,4}\s*[-/]?\s*\d{2,}/i.test(t) || /^[A-Z]{2,}\d{3,}/i.test(t);
}

function looksLikeModuleName(s) {
  const t = String(s || "").replace(/\s+/g, " ").trim();
  if (!t || t.length < 4) return false;
  if (looksLikeModuleCode(t)) return false;
  if (/^\d{1,2}:\d{2}/.test(t)) return false;
  if (/^(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i.test(t)) return false;
  // Course titles are usually multi-word or long single words with letters
  if (!/[a-zA-Z]{3,}/.test(t)) return false;
  return t.length >= 6 || /\s/.test(t);
}

/** Resolve code + name from a data row, fixing swapped / blank-header columns. */
function resolveModuleFields(cells, colMap) {
  const get = (key) => {
    const idx = colMap[key];
    return idx == null ? "" : String(cells[idx] || "").trim();
  };

  let code = get("module_code");
  let name = get("module_name");

  // Name column accidentally holds the code (merged "Module" header over code col)
  if (name && looksLikeModuleCode(name) && !code) {
    code = name;
    name = "";
    const nameIdx = (colMap.module_name ?? colMap.module_code ?? 0) + 1;
    if (looksLikeModuleName(cells[nameIdx])) name = String(cells[nameIdx]).trim();
  }

  // Code+name both look like codes, or name empty → peek next cell after code
  if (code && (!name || looksLikeModuleCode(name) || name === code)) {
    const codeIdx = colMap.module_code != null ? colMap.module_code : colMap.module_name;
    if (codeIdx != null) {
      const taken = new Set(
        Object.values(colMap).filter((v) => typeof v === "number" && v !== colMap.module_name)
      );
      // Prefer explicit module_name index if it has a real title
      if (colMap.module_name != null && looksLikeModuleName(cells[colMap.module_name]) && !looksLikeModuleCode(cells[colMap.module_name])) {
        name = String(cells[colMap.module_name]).trim();
      } else {
        for (const delta of [1, 2]) {
          const idx = codeIdx + delta;
          if (taken.has(idx) && idx !== colMap.module_name) continue;
          if (looksLikeModuleName(cells[idx])) {
            name = String(cells[idx]).trim();
            break;
          }
        }
      }
    }
  }

  // Swapped: code col has title, name col has code
  if (looksLikeModuleName(code) && looksLikeModuleCode(name)) {
    const tmp = code;
    code = name;
    name = tmp;
  }

  return { module_code: code, module_name: name };
}

export function parseSectionTitle(text) {
  const t = String(text || "")
    .replace(/\s+/g, " ")
    .trim();
  if (!t || t.length < 8) return null;
  if (/^day\b/i.test(t) && /module/i.test(t)) return null;
  if (/^day\s*$/i.test(t)) return null;

  const hasYear = /year\s*\d+/i.test(t);
  const hasGroup = /group\s*\d+/i.test(t);
  const hasProgram =
    /bachelor|master|diploma|honou?rs|bba|programme|program|accounting|transport|finance|management|science|education|engineering/i.test(
      t
    );
  if (!(hasYear || (hasGroup && hasProgram) || (hasGroup && t.length > 40))) return null;
  if (/^[A-Z]{2,}\d{3,}/i.test(t) && t.length < 40) return null;

  const yearM = t.match(/year\s*(\d+)/i);
  const year = yearM ? parseInt(yearM[1], 10) : 0;
  const afterYear = t.replace(/^year\s*\d+\s*[:\-–]?\s*/i, "");
  const program_hint = afterYear.split(/,?\s*GROUP/i)[0].trim();
  return { title: t, year, program_hint, group_hint: t };
}

function looksLikeDataRow(cells, colMap) {
  if (!colMap) return false;
  const day = colMap.day != null ? String(cells[colMap.day] || "") : "";
  const time = colMap.time != null ? String(cells[colMap.time] || "") : "";
  const code = colMap.module_code != null ? String(cells[colMap.module_code] || "") : "";
  if (normalizeDayName(day) && /monday|tuesday|wednesday|thursday|friday|saturday|sunday/i.test(normalizeDayName(day)))
    return true;
  if (/\d{1,2}:\d{2}/.test(time)) return true;
  if (/^[A-Z]{2,}\s*\d{3,}/i.test(code.trim())) return true;
  return false;
}

export function parseSheetToSections(aoa, sheetName = "") {
  const sections = [];
  let current = null;
  let colMap = null;
  let lastColMap = null;
  let lastDay = "";
  let titlesSeen = 0;

  for (let r = 0; r < aoa.length; r += 1) {
    const row = aoa[r] || [];
    const cells = row.map((c) => (c == null ? "" : String(c).replace(/\r?\n/g, " ").trim()));
    const first = cells[0] || "";
    const longest = cells.reduce((a, b) => (String(b).length > String(a).length ? b : a), "");
    const line = cells.filter(Boolean).join(" ").trim();
    if (!line) continue;

    if (isHeaderRow(cells)) {
      colMap = findColMap(cells);
      // Two-row headers: next row may be "Code" | "Name" under a merged Module
      const nextRow = aoa[r + 1] || [];
      const nextCells = nextRow.map((c) => (c == null ? "" : String(c).replace(/\r?\n/g, " ").trim()));
      const nextJoined = nextCells.map(normalizeHeader).filter(Boolean);
      const nextIsSub =
        nextJoined.length > 0 &&
        nextJoined.length <= 6 &&
        !isHeaderRow(nextCells) &&
        nextJoined.every((t) =>
          /^(code|name|module|course|title|credits?|lecturer|room|venue|group|campus|program|year)/i.test(t)
        );
      if (nextIsSub) {
        const sub = findColMap(nextCells);
        if (sub.module_code != null) colMap.module_code = sub.module_code;
        if (sub.module_name != null) colMap.module_name = sub.module_name;
        if (sub.credits != null) colMap.credits = sub.credits;
        r += 1; // skip sub-header row — for-loop will r++ again? NO - we must not double skip incorrectly
        // We're inside for (r++) — incrementing r here skips next iteration's row by making r point to subheader, then for adds 1. So after continue, r becomes subheader+1. Good if we set r to subheader index... 
        // Currently r is header index. Setting r += 1 makes r = subheader. Loop end does r++ → data row. Correct.
      }
      lastColMap = colMap;
      if (!current) {
        current = {
          title: `${sheetName ? `${sheetName} — ` : ""}Untitled section`,
          year: 0,
          program_hint: sheetName || "",
          group_hint: "",
          sheet: sheetName || "",
          rows: [],
        };
        sections.push(current);
      }
      continue;
    }

    const sec = parseSectionTitle(line) || parseSectionTitle(first) || parseSectionTitle(longest);
    if (sec && !looksLikeDataRow(cells, colMap || lastColMap)) {
      titlesSeen += 1;
      current = { ...sec, sheet: sheetName || "", rows: [] };
      sections.push(current);
      colMap = lastColMap;
      lastDay = "";
      continue;
    }

    const activeMap = colMap || lastColMap;
    if (!current || !activeMap) continue;

    const get = (key) => {
      const idx = activeMap[key];
      return idx == null ? "" : cells[idx] || "";
    };

    let day = get("day");
    if (day) lastDay = normalizeDayName(day);
    else day = lastDay;
    day = normalizeDayName(day);

    const timeRaw = get("time");
    if (!timeRaw && !get("module_code") && !get("module_name")) continue;
    const tm = parseExcelTime(timeRaw);
    if (!tm.start && !get("module_code") && !get("module_name")) continue;

    const { module_code, module_name } = resolveModuleFields(cells, activeMap);

    const creditsRaw = get("credits");
    const creditsParsed = creditsRaw !== "" && creditsRaw != null ? parseInt(String(creditsRaw).replace(/[^\d]/g, ""), 10) : null;

    current.rows.push({
      day,
      start: tm.start,
      end: tm.end,
      time_raw: tm.time_raw,
      time_group_nums: tm.time_group_nums,
      module_code,
      module_name,
      credits: Number.isFinite(creditsParsed) ? creditsParsed : null,
      lecturers: get("lecturers"),
      classroom: get("classroom"),
      room_capacity: parseInt(get("room_capacity"), 10) || null,
      students: parseInt(get("students"), 10) || null,
    });
  }

  const kept = sections.filter((s) => s.rows.length > 0);
  return {
    sections: kept,
    meta: {
      titlesSeen,
      sectionsWithRows: kept.length,
      emptySections: sections.length - kept.length,
    },
  };
}

export function parseTimetableWorkbook(XLSX, bufferOrWb) {
  const wb =
    bufferOrWb?.SheetNames != null
      ? bufferOrWb
      : XLSX.read(bufferOrWb, { type: bufferOrWb instanceof ArrayBuffer ? "array" : "buffer", raw: false });

  const all = [];
  const meta = { sheets: 0, titlesSeen: 0, emptySections: 0, sheetNames: [] };
  (wb.SheetNames || []).forEach((name) => {
    if (/allocated rooms|nolonger|no longer/i.test(name)) return;
    const sheet = wb.Sheets[name];
    if (!sheet) return;
    meta.sheets += 1;
    meta.sheetNames.push(name);
    const aoa = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: "", raw: false });
    const parts = parseSheetToSections(aoa, name);
    meta.titlesSeen += parts.meta.titlesSeen || 0;
    meta.emptySections += parts.meta.emptySections || 0;
    parts.sections.forEach((s) => all.push(s));
  });
  return { sections: all, meta, workbook: wb };
}
