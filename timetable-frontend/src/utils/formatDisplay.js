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

/** Campus names: "huye" / "HUYE" → "Huye" */
export function capitalizeCampusName(name) {
  return capitalizePersonName(name);
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
    user.campus?.name ? capitalizeCampusName(user.campus.name) : null,
  ].filter(Boolean);
  return bits.join(' · ');
}

/** Primary line in facility picker / button — name (cap) · building */
export function facilityPickerLabel(f) {
  if (!f) return '';
  const name = f.name || 'Facility';
  const cap = f.capacity != null && f.capacity !== '' ? ` (${f.capacity})` : '';
  const building = f.buildName ? ` · ${f.buildName}` : '';
  return `${name}${cap}${building}`;
}

/** Secondary line: building code, campus, type, site */
export function facilityPickerMeta(f) {
  if (!f) return '';
  const campusRaw = f.campus?.name || (typeof f.campus === 'string' ? f.campus : null);
  return [
    f.buildCode ? `Code: ${f.buildCode}` : null,
    f.site ? `Site: ${f.site}` : null,
    campusRaw ? capitalizeCampusName(campusRaw) : null,
    f.type || null,
    f.name2 && String(f.name2) !== String(f.name) ? f.name2 : null,
  ]
    .filter(Boolean)
    .join(' · ');
}

/** Compact label for picker buttons / table cells: AUDI (252) · Building */
export function facilityCompactLabel(f) {
  if (!f) return '';
  const name = f.name || 'Facility';
  const cap = f.capacity != null && f.capacity !== '' ? ` (${f.capacity})` : '';
  const building = f.buildName ? ` · ${f.buildName}` : '';
  const campusRaw = f.campus?.name || (typeof f.campus === 'string' ? f.campus : null);
  const campus = campusRaw ? capitalizeCampusName(campusRaw) : null;
  return `${name}${cap}${building}${campus ? ` · ${campus}` : ''}`;
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
