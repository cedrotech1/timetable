import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import { fmtTime } from './timeFormat.js';
import { capitalizePersonName, facilityCompactLabel } from './formatDisplay.js';

function safeFileName(name) {
  const base = String(name || 'timetable-preview')
    .replace(/[^\w\-]+/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '')
    .slice(0, 80);
  return `${base || 'timetable-preview'}.pdf`;
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

/**
 * Build and download a PDF of upload-preview table(s) — no popup / print dialog.
 * @param {object[]} sections matched upload sections
 * @param {{ yearLabel?: string, academicYearId?: number|string, semester?: string|number, fileName?: string }} meta
 */
export function exportUploadSectionsPdf(sections, meta = {}) {
  const list = (sections || []).filter(Boolean);
  if (!list.length) {
    throw new Error('No section to export');
  }

  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
  const pageW = doc.internal.pageSize.getWidth();
  const margin = 10;
  const generated = new Date().toLocaleString();
  const ay = meta.yearLabel || (meta.academicYearId ? `AY #${meta.academicYearId}` : '');
  const sem = meta.semester != null && meta.semester !== '' ? `Semester ${meta.semester}` : '';
  const title =
    list.length === 1
      ? `Timetable preview — ${list[0].title || 'section'}`
      : `Timetable preview — ${list.length} sections`;

  let y = margin;
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(13);
  doc.setTextColor(3, 31, 80);
  doc.text(title, margin, y);
  y += 6;
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.setTextColor(100, 116, 139);
  doc.text(
    `Upload preview before save · Generated ${generated}${[ay, sem].filter(Boolean).length ? ` · ${[ay, sem].filter(Boolean).join(' · ')}` : ''}`,
    margin,
    y
  );
  y += 8;

  list.forEach((sec, idx) => {
    if (idx > 0) {
      doc.addPage();
      y = margin;
    }

    const stats = sectionConflictStats(sec);
    const rows = (sec.rows || []).filter((r) => r.status !== 'skipped' && !r.mergedAway);
    const program = sec.program?.name || 'Program not matched';

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(3, 31, 80);
    const titleLines = doc.splitTextToSize(sec.title || 'Untitled section', pageW - margin * 2);
    doc.text(titleLines, margin, y);
    y += titleLines.length * 5 + 2;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.5);
    doc.setTextColor(71, 85, 105);
    const metaLine = `Year ${sec.year || '—'} · Groups ${(sec.groupNumbers || []).join('&') || '—'} · ${program} · ${rows.length} row(s)`;
    doc.text(doc.splitTextToSize(metaLine, pageW - margin * 2), margin, y);
    y += 5;

    if (stats.total) {
      doc.setTextColor(180, 83, 9);
      doc.setFont('helvetica', 'bold');
      const conflictLine = `${stats.total} conflict warning(s) — ${[
        stats.room ? `${stats.room} ROOM` : null,
        stats.group ? `${stats.group} GROUP` : null,
        stats.both ? `${stats.both} ROOM+GROUP` : null,
      ]
        .filter(Boolean)
        .join(' · ')} (review before save)`;
      doc.text(doc.splitTextToSize(conflictLine, pageW - margin * 2), margin, y);
      y += 5;
    } else {
      doc.setTextColor(4, 120, 87);
      doc.text('No ROOM/GROUP conflicts flagged on this preview', margin, y);
      y += 5;
    }

    const body = rows.map((r) => {
      const status = rowStatus(r);
      const detail = conflictDetails(r);
      const moduleCell = detail ? `${moduleLabel(r)}\n${detail}` : moduleLabel(r);
      const fac = facilityLabel(r);
      const lec = lecturersLabel(r);
      return [
        status,
        `${r.day || '—'} ${fmtTime(r.start)}-${fmtTime(r.end)}`,
        moduleCell,
        fac,
        lec,
        groupsLabel(r),
      ];
    });

    autoTable(doc, {
      startY: y,
      margin: { left: margin, right: margin },
      head: [['Status', 'Day / Time', 'Module', 'Facility', 'Lecturers', 'Groups']],
      body: body.length ? body : [['—', '—', 'No rows', '—', '—', '—']],
      styles: {
        fontSize: 7.5,
        cellPadding: 1.8,
        valign: 'top',
        overflow: 'linebreak',
        lineColor: [203, 213, 225],
        lineWidth: 0.2,
      },
      headStyles: {
        fillColor: [3, 31, 80],
        textColor: 255,
        fontStyle: 'bold',
        fontSize: 8,
      },
      columnStyles: {
        0: { cellWidth: 28, fontStyle: 'bold' },
        1: { cellWidth: 32 },
        2: { cellWidth: 70 },
        3: { cellWidth: 40 },
        4: { cellWidth: 55 },
        5: { cellWidth: 30 },
      },
      didParseCell(data) {
        if (data.section !== 'body') return;
        const status = String(data.row.raw?.[0] || '');
        if (/^CONFLICT/i.test(status)) {
          data.cell.styles.fillColor = [255, 247, 237];
          if (data.column.index === 0 || data.column.index === 2) {
            data.cell.styles.textColor = [194, 65, 12];
          }
        }
      },
    });

    y = (doc.lastAutoTable?.finalY || y) + 8;
  });

  const fileName = safeFileName(meta.fileName || (list.length === 1 ? list[0].title : 'timetable-preview-all-sections'));
  doc.save(fileName);
  return fileName;
}
