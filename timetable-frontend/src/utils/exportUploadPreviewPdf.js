import { fmtTime } from './timeFormat.js';
import { capitalizePersonName, facilityCompactLabel } from './formatDisplay.js';

function esc(s) {
  return String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function rowStatus(row) {
  if (row.status === 'skipped' || row.mergedAway) return 'Merged';
  const hasConflict =
    row.saveConflict &&
    ((row.saveConflict.facility || []).length > 0 || Object.keys(row.saveConflict.groups || {}).length > 0);
  if (hasConflict) {
    const kinds = [];
    if ((row.saveConflict.facility || []).length) kinds.push('ROOM');
    if (Object.keys(row.saveConflict.groups || {}).length) kinds.push('GROUP');
    return `CONFLICT (${kinds.join('+')})`;
  }
  if (row.combinedClass && (row.groups || []).length > 1) return 'Combined';
  if (row.status === 'ok') return 'OK';
  if (row.status === 'error') return 'Error';
  if (row.status === 'warning') return 'Warning';
  return String(row.status || '—');
}

function conflictDetails(row) {
  const c = row.saveConflict;
  if (!c) return '';
  const bits = [];
  for (const f of c.facility || []) {
    bits.push(f.simpleReason || `Room clash with ${f.moduleCode || f.moduleName || 'another class'}`);
  }
  for (const list of Object.values(c.groups || {})) {
    for (const g of list || []) {
      bits.push(g.simpleReason || `Group clash: ${g.groupName || g.groupId}`);
    }
  }
  for (const w of row.warnings || []) {
    if (/ROOM|GROUP|conflict/i.test(String(w))) bits.push(String(w));
  }
  return [...new Set(bits)].join(' · ');
}

function moduleLabel(row) {
  const code = row.excel?.moduleCode || row.module?.code || '';
  const name = row.excel?.moduleName || row.module?.name || '';
  if (code && name && name !== code) return `${code} — ${name}`;
  return name || code || '—';
}

function facilityLabel(row) {
  if (row.facility) {
    return facilityCompactLabel(row.facility) || row.facility.name || '—';
  }
  return row.excel?.classroom || '—';
}

function lecturersLabel(row) {
  const parts = [];
  const leader = row.lecturers?.leader;
  if (leader?.names) parts.push(`${capitalizePersonName(leader.names)} (ML)`);
  for (const o of row.lecturers?.others || []) {
    if (o?.names) parts.push(capitalizePersonName(o.names));
  }
  if (parts.length) return parts.join(', ');
  return row.excel?.lecturers || '—';
}

function groupsLabel(row) {
  const names = (row.groups || []).map((g) => g.name).filter(Boolean);
  return names.length ? names.join(', ') : '—';
}

function sectionConflictStats(sec) {
  let room = 0;
  let group = 0;
  let both = 0;
  for (const row of sec.rows || []) {
    if (row.status === 'skipped' || row.mergedAway) continue;
    const c = row.saveConflict;
    if (!c) continue;
    const hasF = (c.facility || []).length > 0;
    const hasG = Object.keys(c.groups || {}).length > 0;
    if (hasF && hasG) both += 1;
    else if (hasF) room += 1;
    else if (hasG) group += 1;
  }
  return { room, group, both, total: room + group + both };
}

function buildSectionHtml(sec, meta = {}) {
  const stats = sectionConflictStats(sec);
  const rows = (sec.rows || []).filter((r) => r.status !== 'skipped' && !r.mergedAway);
  const program = sec.program?.name || 'Program not matched';
  const ay = meta.yearLabel || (meta.academicYearId ? `AY #${meta.academicYearId}` : '');
  const sem = meta.semester != null && meta.semester !== '' ? `Semester ${meta.semester}` : '';

  const body = rows
    .map((r) => {
      const status = rowStatus(r);
      const isConflict = /^CONFLICT/i.test(status);
      const detail = conflictDetails(r);
      const when = `${r.day || '—'} ${fmtTime(r.start)}-${fmtTime(r.end)}`;
      return `<tr class="${isConflict ? 'conflict' : ''}">
        <td class="status">${esc(status)}</td>
        <td class="nowrap">${esc(when)}</td>
        <td>${esc(moduleLabel(r))}${detail ? `<div class="detail">${esc(detail)}</div>` : ''}</td>
        <td>${esc(facilityLabel(r))}${r.excel?.classroom ? `<div class="muted">Excel: ${esc(r.excel.classroom)}</div>` : ''}</td>
        <td>${esc(lecturersLabel(r))}${r.excel?.lecturers ? `<div class="muted">Excel: ${esc(r.excel.lecturers)}</div>` : ''}</td>
        <td>${esc(groupsLabel(r))}</td>
      </tr>`;
    })
    .join('');

  return `
    <section class="section">
      <h2>${esc(sec.title || 'Untitled section')}</h2>
      <p class="meta">
        ${esc([ay, sem].filter(Boolean).join(' · '))}
        ${ay || sem ? '<br/>' : ''}
        Year ${esc(sec.year || '—')} · Groups ${(sec.groupNumbers || []).join('&') || '—'} · ${esc(program)}
        · ${rows.length} row(s)
        ${
          stats.total
            ? `<br/><strong class="warn">${stats.total} conflict warning(s)</strong> — ${[
                stats.room ? `${stats.room} ROOM` : null,
                stats.group ? `${stats.group} GROUP` : null,
                stats.both ? `${stats.both} ROOM+GROUP` : null,
              ]
                .filter(Boolean)
                .join(' · ')} (review before save)`
            : '<br/><span class="ok">No ROOM/GROUP conflicts flagged on this preview</span>'
        }
      </p>
      <table>
        <thead>
          <tr>
            <th>Status</th>
            <th>Day / Time</th>
            <th>Module</th>
            <th>Facility</th>
            <th>Lecturers</th>
            <th>Groups</th>
          </tr>
        </thead>
        <tbody>${body || '<tr><td colspan="6">No rows</td></tr>'}</tbody>
      </table>
    </section>`;
}

/**
 * Open a print window for upload-preview table(s) so the user can Save as PDF.
 * @param {object[]} sections matched upload sections
 * @param {{ yearLabel?: string, academicYearId?: number|string, semester?: string|number, fileName?: string }} meta
 */
export function exportUploadSectionsPdf(sections, meta = {}) {
  const list = (sections || []).filter(Boolean);
  if (!list.length) {
    throw new Error('No section to export');
  }

  const generated = new Date().toLocaleString();
  const title =
    list.length === 1
      ? `Timetable preview — ${list[0].title || 'section'}`
      : `Timetable preview — ${list.length} sections`;

  const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>${esc(title)}</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: "Segoe UI", Tahoma, sans-serif; font-size: 11px; color: #0f172a; margin: 0; padding: 12px; }
    h1 { font-size: 16px; margin: 0 0 4px; color: #031f50; }
    .sub { color: #64748b; margin: 0 0 16px; font-size: 11px; }
    .section { break-inside: avoid; page-break-inside: avoid; margin-bottom: 22px; }
    .section h2 { font-size: 13px; margin: 0 0 6px; color: #031f50; }
    .meta { margin: 0 0 10px; color: #475569; line-height: 1.45; }
    .warn { color: #b45309; }
    .ok { color: #047857; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: top; word-wrap: break-word; }
    th { background: #031f50; color: #fff; text-align: left; font-size: 10px; }
    tr.conflict td { background: #fff7ed; }
    td.status { font-weight: 700; font-size: 10px; width: 11%; }
    td.nowrap { white-space: nowrap; width: 12%; }
    .detail { margin-top: 3px; color: #c2410c; font-size: 10px; font-weight: 600; }
    .muted { margin-top: 2px; color: #94a3b8; font-size: 9px; }
    .toolbar { margin-bottom: 12px; }
    .toolbar button {
      background: #00628b; color: #fff; border: 0; border-radius: 8px;
      padding: 8px 14px; font-weight: 600; cursor: pointer; font-size: 12px;
    }
    @media print {
      .toolbar { display: none !important; }
      body { padding: 0; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button type="button" onclick="window.print()">Save / Print as PDF</button>
    <span style="margin-left:8px;color:#64748b">Use your browser’s “Save as PDF” printer.</span>
  </div>
  <h1>${esc(title)}</h1>
  <p class="sub">Upload preview before save · Generated ${esc(generated)} · Conflicts highlighted for review</p>
  ${list.map((sec) => buildSectionHtml(sec, meta)).join('')}
  <script>
    window.addEventListener('load', function () {
      setTimeout(function () { window.focus(); window.print(); }, 250);
    });
  </script>
</body>
</html>`;

  const w = window.open('', '_blank', 'noopener,noreferrer,width=1200,height=800');
  if (!w) {
    throw new Error('Pop-up blocked — allow pop-ups to download the PDF preview');
  }
  w.document.open();
  w.document.write(html);
  w.document.close();
  try {
    w.document.title = meta.fileName || title;
  } catch {
    /* ignore */
  }
  return true;
}
