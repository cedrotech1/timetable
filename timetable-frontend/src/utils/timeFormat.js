/**
 * Flexible session clocks for display, overlap checks, and Excel import.
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
    // Already HH:MM(:SS) with junk trimmed failed above — last resort slice
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

/** Display HH:MM (empty → "") */
export function fmtTime(t) {
  return normalizeClock(t) || "";
}

/** Minutes from midnight; fallback used when unparseable */
export function toMinutes(t, fallback = 0) {
  const s = normalizeClock(t);
  if (!s) return fallback;
  const [h, m] = s.split(":").map(Number);
  if (!Number.isFinite(h)) return fallback;
  return h * 60 + (Number.isFinite(m) ? m : 0);
}

/** Range string → { start, end } as HH:MM */
export function parseTimeRange(raw) {
  const text = String(raw || "")
    .replace(/\u00a0/g, " ")
    .replace(/\(?\s*(?:GP|G|Group)\s*[0-9]+(?:\s*[&,]\s*[0-9]+)*\s*\)?/gi, " ")
    .replace(/\s+/g, " ")
    .trim();
  const rangeMatch = text.match(/(.+?)\s*(?:[-–—]|to)\s*(.+)$/i);
  if (!rangeMatch) return { start: "", end: "", time_raw: text };
  return {
    start: normalizeClock(rangeMatch[1]),
    end: normalizeClock(rangeMatch[2]),
    time_raw: text,
  };
}

export function sessionsOverlap(a, b) {
  if (!a?.day || !b?.day || String(a.day).toLowerCase() !== String(b.day).toLowerCase()) return false;
  const a0 = toMinutes(a.start || a.startTime || a.start_time);
  const a1 = toMinutes(a.end || a.endTime || a.end_time);
  const b0 = toMinutes(b.start || b.startTime || b.start_time);
  const b1 = toMinutes(b.end || b.endTime || b.end_time);
  return a0 < b1 && b0 < a1;
}
