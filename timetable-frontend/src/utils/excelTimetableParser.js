/**
 * Parse UR-style timetable Excel (sample.xls) into Year/Group sections + rows.
 * Mirrors PHP Dashboard/timetable_import_excel.php parsers.
 */

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
    .replace(/\s+/g, " ")
    .trim();
  const timeGroupNums = [];
  const gp = text.match(/\(?\s*(?:GP|G|Group)\s*([0-9]+(?:\s*[&,]\s*[0-9]+)*)\s*\)?/i);
  if (gp) (gp[1].match(/\d+/g) || []).forEach((n) => timeGroupNums.push(parseInt(n, 10)));

  const m = text.match(/(\d{1,2}):(\d{2})\s*(AM|PM)?\s*[-–—to]+\s*(\d{1,2}):(\d{2})\s*(AM|PM)?/i);
  if (!m) return { start: "", end: "", time_raw: text, time_group_nums: timeGroupNums };

  const to24 = (h, min, ampm) => {
    let hh = parseInt(h, 10);
    const ap = (ampm || "").toUpperCase();
    if (ap === "PM" && hh < 12) hh += 12;
    if (ap === "AM" && hh === 12) hh = 0;
    return `${String(hh).padStart(2, "0")}:${min}`;
  };

  let start = to24(m[1], m[2], m[3] || "");
  let end = to24(m[4], m[5], m[6] || "");
  if (parseInt(m[1], 10) >= 13) start = `${String(parseInt(m[1], 10)).padStart(2, "0")}:${m[2]}`;
  if (parseInt(m[4], 10) >= 13) end = `${String(parseInt(m[4], 10)).padStart(2, "0")}:${m[5]}`;
  return { start, end, time_raw: text, time_group_nums: timeGroupNums };
}

function isHeaderRow(cells) {
  const parts = cells.map((c) =>
    String(c || "")
      .toLowerCase()
      .replace(/\s+/g, " ")
      .trim()
  );
  const joined = parts.join(" | ");
  const hasDay = parts.some((t) => t === "day" || t.startsWith("day "));
  const hasModule = parts.some(
    (t) =>
      t.includes("module code") ||
      t.includes("module/course") ||
      t === "module" ||
      (t.includes("module") && t.includes("name")) ||
      t.includes("course name")
  );
  const hasTime = parts.some((t) => t === "time" || t.includes("time"));
  return hasDay && hasModule && (hasTime || joined.includes("lecturer") || joined.includes("classroom"));
}

function findColMap(cells) {
  const map = {};
  cells.forEach((c, i) => {
    const t = String(c || "")
      .toLowerCase()
      .replace(/\s+/g, " ")
      .trim();
    if (t === "day" || /^day\b/.test(t)) map.day = i;
    else if (t === "time" || /^time\b/.test(t)) map.time = i;
    else if (t.includes("module code") || t === "code") map.module_code = i;
    else if (t.includes("module/course") || t.includes("course name") || (t.includes("module") && t.includes("name")))
      map.module_name = i;
    else if (/^lecturer/.test(t) && !t.includes("no of") && !t.includes("number")) map.lecturers = i;
    else if (t.includes("room capacity") || (t.includes("capacity") && !t.includes("student"))) map.room_capacity = i;
    else if (t.includes("class room") || t.includes("classroom") || t === "room" || t === "venue") map.classroom = i;
    else if (t.includes("number of students") || t.includes("no of students") || t === "students") map.students = i;
  });
  if (map.lecturers == null) {
    cells.forEach((c, i) => {
      const t = String(c || "")
        .toLowerCase()
        .replace(/\s+/g, " ")
        .trim();
      if (t.includes("lecturer") && !t.includes("no of") && !t.includes("number of lect")) map.lecturers = i;
    });
  }
  return map;
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
    if (!tm.start && !get("module_code")) continue;

    current.rows.push({
      day,
      start: tm.start,
      end: tm.end,
      time_raw: tm.time_raw,
      time_group_nums: tm.time_group_nums,
      module_code: get("module_code"),
      module_name: get("module_name"),
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
