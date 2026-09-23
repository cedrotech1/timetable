/**
 * Flexible session clocks for conflict checks and Excel import.
 * Accepts: 08:00 | 08:00:00 | 08h00 | 8:00 AM | 08.00 | 13:00 PM | a.m./p.m.
 */

/** Single clock → "HH:MM" or "" */
export function normalizeClock(part) {
  if (part == null || part === "") return "";
  const clock = String(part)
    .trim()
    .replace(/\b([ap])\.?\s*m\.?/gi, (_, ap) => ` ${String(ap).toUpperCase()}M `)
    .replace(/\s+/g, " ")
    .trim();

  let m = clock.match(/^(\d{1,2})\s*[:hH.]\s*(\d{2})(?:\s*:\s*\d{2})?\s*(AM|PM)?\s*$/i);
  if (!m) {
    const hm = clock.match(/^(\d{1,2})\s*(AM|PM)\s*$/i);
    if (hm) m = [hm[0], hm[1], "00", hm[2]];
  }
  if (!m) {
    const plain = String(part).trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?/);
    if (plain) {
      const hh = parseInt(plain[1], 10);
      if (hh >= 0 && hh <= 23) return `${String(hh).padStart(2, "0")}:${plain[2]}`;
    }
    return "";
  }

  let hh = parseInt(m[1], 10);
  const min = String(m[2] ?? "00").padStart(2, "0");
  const ap = String(m[3] || "").toUpperCase();

  if (hh >= 13 && hh <= 23) return `${String(hh).padStart(2, "0")}:${min}`;
  if (ap === "PM" && hh < 12) hh += 12;
  if (ap === "AM" && hh === 12) hh = 0;
  if (hh < 0 || hh > 23) return "";
  return `${String(hh).padStart(2, "0")}:${min}`;
}

/** DB / conflict form: "HH:MM:00" or null */
export function normalizeTime(t) {
  if (!t) return null;
  const hhmm = normalizeClock(t);
  if (hhmm) return `${hhmm}:00`;
  const s = String(t).trim();
  if (/^\d{1,2}:\d{2}:\d{2}$/.test(s)) {
    const [h, m, sec] = s.split(":");
    return `${h.padStart(2, "0")}:${m}:${sec}`;
  }
  return s || null;
}

/** Excel / slot form: "HH:MM:00" string (may be empty/raw if unparseable) */
export function normSlotTime(t) {
  const hhmm = normalizeClock(t);
  if (hhmm) return `${hhmm}:00`;
  const s = String(t || "").trim();
  if (/^\d{1,2}:\d{2}:\d{2}$/.test(s)) {
    const [h, m, sec] = s.split(":");
    return `${h.padStart(2, "0")}:${m}:${sec}`;
  }
  return s;
}

export function parseTimeRange(raw) {
  const text = String(raw || "")
    .replace(/\u00a0/g, " ")
    .replace(/\(?\s*(?:GP|G|Group)\s*[0-9]+(?:\s*[&,]\s*[0-9]+)*\s*\)?/gi, " ")
    .replace(/\s+/g, " ")
    .trim();
  const rangeMatch = text.match(/(.+?)\s*(?:[-–—]|to)\s*(.+)$/i);
  if (!rangeMatch) return { start: "", end: "" };
  return {
    start: normalizeClock(rangeMatch[1]),
    end: normalizeClock(rangeMatch[2]),
  };
}

export function timesOverlapStr(aStart, aEnd, bStart, bEnd) {
  const aS = String(normalizeTime(aStart) || aStart || "").slice(0, 8);
  const aE = String(normalizeTime(aEnd) || aEnd || "").slice(0, 8);
  const bS = String(normalizeTime(bStart) || bStart || "").slice(0, 8);
  const bE = String(normalizeTime(bEnd) || bEnd || "").slice(0, 8);
  return aS < bE && aE > bS;
}
