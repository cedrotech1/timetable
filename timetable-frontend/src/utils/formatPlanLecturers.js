/**
 * Format plan lecturers for display/export.
 * Module leader → "Name (ML)"; other lecturers → name only.
 */
import { capitalizePersonName } from './formatDisplay.js';

export function formatPlanLecturers(plan, { asList = false } = {}) {
  const parts = [];
  const leaderRaw =
    plan?.leader_lecturer?.names ||
    plan?.leader?.names ||
    null;
  const leader = leaderRaw ? capitalizePersonName(leaderRaw) : null;
  if (leader) parts.push(`${leader} (ML)`);

  const others = plan?.other_lecturers || plan?.otherLecturers || [];
  for (const l of others) {
    const name = capitalizePersonName(l?.names || l?.name || '');
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
