/**
 * Display helpers for pickers (facilities, lecturers) on Set Timetable.
 */

/** Capitalize each word: "jean DAMASCENE" → "Jean Damascene" */
export function capitalizePersonName(name) {
  if (name == null) return '';
  const raw = String(name).trim();
  if (!raw || raw.startsWith('—') || raw.startsWith('-')) return raw;
  return raw
    .split(/\s+/)
    .map((part) => {
      if (!part) return part;
      // Keep short particles lowercase when mid-name? Prefer full capitalize for staff lists.
      const lower = part.toLowerCase();
      return lower.charAt(0).toUpperCase() + lower.slice(1);
    })
    .join(' ');
}

export function lecturerPickerLabel(user) {
  if (!user) return '';
  if (user.id === '' || user.id == null) {
    return user.names || '— None —';
  }
  return capitalizePersonName(user.names || user.name || '') || String(user.id);
}

export function lecturerPickerMeta(user) {
  if (!user || user.id === '' || user.id == null) return '';
  const bits = [
    user.urEmail || user.email || null,
    user.staffNumber ? `Staff ${user.staffNumber}` : null,
    user.department || null,
    user.academicRank || null,
    user.campus?.name || null,
  ].filter(Boolean);
  return bits.join(' · ');
}

/** Primary line in facility picker / button */
export function facilityPickerLabel(f) {
  if (!f) return '';
  const name = f.name || 'Facility';
  const alt = f.name2 && String(f.name2).trim() && String(f.name2) !== String(f.name) ? String(f.name2).trim() : '';
  return alt ? `${name} (${alt})` : name;
}

/** Secondary line: building, campus, type, capacity, site, code */
export function facilityPickerMeta(f) {
  if (!f) return '';
  const building = [f.buildName, f.buildCode].filter(Boolean).join(' / ');
  return [
    building ? `Building: ${building}` : null,
    f.site ? `Site: ${f.site}` : null,
    f.campus?.name || f.campus || null,
    f.type || null,
    f.capacity != null && f.capacity !== '' ? `Cap ${f.capacity}` : null,
  ]
    .filter(Boolean)
    .join(' · ');
}

/** Compact label for picker buttons / table cells */
export function facilityCompactLabel(f) {
  if (!f) return '';
  const parts = [
    f.name,
    f.buildName ? `· ${f.buildName}` : null,
    f.capacity != null ? `(${f.capacity})` : null,
    f.campus?.name || f.campus || null,
  ].filter(Boolean);
  return parts.join(' ');
}

export function facilitySearchHaystack(f) {
  return [
    f?.name,
    f?.name2,
    f?.buildName,
    f?.buildCode,
    f?.site,
    f?.type,
    f?.campus?.name || f?.campus,
    f?.capacity,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase();
}
