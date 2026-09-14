/**
 * Format plan lecturers for display/export.
 * Module leader → "Name (ML)"; other lecturers → name only.
 */
export function formatPlanLecturers(plan, { asList = false } = {}) {
  const parts = [];
  const leader =
    plan?.leader_lecturer?.names ||
    plan?.leader?.names ||
    null;
  if (leader) parts.push(`${leader} (ML)`);

  const others = plan?.other_lecturers || plan?.otherLecturers || [];
  for (const l of others) {
    const name = l?.names || l?.name;
    if (!name) continue;
    // Avoid duplicating the leader if they also appear in others
    if (leader && String(name).trim().toLowerCase() === String(leader).trim().toLowerCase()) {
      continue;
    }
    parts.push(name);
  }

  if (asList) return parts;
  return parts.length ? parts.join(', ') : '—';
}
