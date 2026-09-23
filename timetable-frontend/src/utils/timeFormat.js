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

/**
 * Scan saved teaching plans for ROOM + GROUP overlaps (same rules as save conflicts).
 * Returns Map<planId, { kinds: string[], facility: [...], groups: [...], summary: string }>
 */
export function detectTimetableConflicts(plans = []) {
  const byId = new Map();
  const list = (plans || []).filter((p) => p?.id != null);

  const ensure = (id) => {
    if (!byId.has(id)) byId.set(id, { kinds: new Set(), facility: [], groups: [], peers: new Set() });
    return byId.get(id);
  };

  const planLabel = (p) =>
    [p.code || p.module?.code, p.course || p.module?.name].filter(Boolean).join(" — ") || `Plan #${p.id}`;

  const sessionPairsOverlap = (aSessions, bSessions) => {
    for (const sa of aSessions || []) {
      for (const sb of bSessions || []) {
        if (sessionsOverlap(sa, sb)) return { sa, sb };
      }
    }
    return null;
  };

  for (let i = 0; i < list.length; i += 1) {
    for (let j = i + 1; j < list.length; j += 1) {
      const a = list[i];
      const b = list[j];
      const hit = sessionPairsOverlap(a.sessions, b.sessions);
      if (!hit) continue;

      const sameFacility =
        a.facility?.id != null &&
        b.facility?.id != null &&
        Number(a.facility.id) === Number(b.facility.id);

      const aGroups = new Set((a.groups || []).map((g) => Number(g.id)).filter(Boolean));
      const sharedGroups = (b.groups || []).filter((g) => aGroups.has(Number(g.id)));

      if (!sameFacility && !sharedGroups.length) continue;

      const when = `${hit.sa.day || hit.sb.day} ${fmtTime(hit.sa.start_time || hit.sa.startTime)}–${fmtTime(hit.sa.end_time || hit.sa.endTime)}`;

      if (sameFacility) {
        const entryA = ensure(a.id);
        const entryB = ensure(b.id);
        entryA.kinds.add("facility");
        entryB.kinds.add("facility");
        entryA.peers.add(b.id);
        entryB.peers.add(a.id);
        entryA.facility.push({
          peerId: b.id,
          peerLabel: planLabel(b),
          when,
          facilityName: a.facility?.name || b.facility?.name,
          simpleReason: `ROOM conflict with #${b.id} (${planLabel(b)})`,
        });
        entryB.facility.push({
          peerId: a.id,
          peerLabel: planLabel(a),
          when,
          facilityName: a.facility?.name || b.facility?.name,
          simpleReason: `ROOM conflict with #${a.id} (${planLabel(a)})`,
        });
      }

      if (sharedGroups.length) {
        const entryA = ensure(a.id);
        const entryB = ensure(b.id);
        entryA.kinds.add("group");
        entryB.kinds.add("group");
        entryA.peers.add(b.id);
        entryB.peers.add(a.id);
        const names = sharedGroups.map((g) => g.name).filter(Boolean).join(", ");
        entryA.groups.push({
          peerId: b.id,
          peerLabel: planLabel(b),
          when,
          groupsLabel: names,
          simpleReason: `GROUP conflict (${names || "shared group"}) with #${b.id}`,
        });
        entryB.groups.push({
          peerId: a.id,
          peerLabel: planLabel(a),
          when,
          groupsLabel: names,
          simpleReason: `GROUP conflict (${names || "shared group"}) with #${a.id}`,
        });
      }
    }
  }

  const out = new Map();
  for (const [id, v] of byId) {
    const kinds = [...v.kinds];
    let summary = "Conflict";
    if (kinds.includes("facility") && kinds.includes("group")) summary = "ROOM + GROUP conflict";
    else if (kinds.includes("facility")) summary = "ROOM conflict";
    else if (kinds.includes("group")) summary = "GROUP conflict";
    out.set(id, {
      kinds,
      facility: v.facility,
      groups: v.groups,
      peerIds: [...v.peers],
      summary,
    });
  }
  return out;
}
