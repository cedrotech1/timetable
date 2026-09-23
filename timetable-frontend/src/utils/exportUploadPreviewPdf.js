/**
 * Zero-dependency PDF download for Excel upload preview tables.
 * Avoids jspdf/pako so deploy works on flaky npm networks.
 */
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

function pdfEscape(s) {
  return String(s ?? '')
    .replace(/\\/g, '\\\\')
    .replace(/\(/g, '\\(')
    .replace(/\)/g, '\\)')
    .replace(/\r?\n/g, ' ')
    .replace(/[^\x20-\x7E]/g, (ch) => {
      // Keep latin accents roughly by stripping; PDF Helvetica is WinAnsi-ish via simple ASCII fallback
      try {
        return ch.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      } catch {
        return '?';
      }
    });
}

function wrapWords(text, maxChars) {
  const words = String(text || '').split(/\s+/).filter(Boolean);
  const lines = [];
  let cur = '';
  for (const w of words) {
    const next = cur ? `${cur} ${w}` : w;
    if (next.length <= maxChars) cur = next;
    else {
      if (cur) lines.push(cur);
      if (w.length > maxChars) {
        for (let i = 0; i < w.length; i += maxChars) lines.push(w.slice(i, i + maxChars));
        cur = '';
      } else cur = w;
    }
  }
  if (cur) lines.push(cur);
  return lines.length ? lines : [''];
}

/** Minimal multi-page landscape PDF writer (Helvetica only). */
function buildSimplePdf(pages) {
  // pages: array of { lines: string[] } where lines are already laid out as draw commands helpers
  const objs = [];
  const add = (content) => {
    objs.push(content);
    return objs.length;
  };

  const fontObj = add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
  const fontBoldObj = add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');

  const pageIds = [];
  const contentIds = [];

  for (const page of pages) {
    const stream = page.stream;
    const contentId = add(`<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`);
    contentIds.push(contentId);
    const pageId = add(null); // placeholder
    pageIds.push(pageId);
  }

  // Fill page objects (need kids refs later)
  const pagesObjId = objs.length + 1; // will push after filling pages
  // Actually we need pages dict after page objects. Rebuild carefully:

  const catalogId = add(null);
  const pagesDictId = add(null);

  // Reset and rebuild with known structure
  const out = [];
  const offsets = [0];

  const writeObj = (id, body) => {
    offsets[id] = out.join('').length;
    out.push(`${id} 0 obj\n${body}\nendobj\n`);
  };

  // Object 1: font
  // Object 2: font bold
  // Object 3..: content streams
  // then page objs
  // then pages
  // then catalog

  let id = 1;
  const FONT = id++;
  writeObj(FONT, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
  const FONTB = id++;
  writeObj(FONTB, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');

  const contentObjIds = [];
  for (const page of pages) {
    const cid = id++;
    contentObjIds.push(cid);
    const stream = page.stream;
    writeObj(cid, `<< /Length ${Buffer.byteLength(stream, 'utf8')} >>\nstream\n${stream}\nendstream`);
  }

  const pageObjIds = [];
  for (let i = 0; i < pages.length; i += 1) {
    const pid = id++;
    pageObjIds.push(pid);
  }
  const PAGES = id++;
  const CATALOG = id++;

  for (let i = 0; i < pageObjIds.length; i += 1) {
    writeObj(
      pageObjIds[i],
      `<< /Type /Page /Parent ${PAGES} 0 R /MediaBox [0 0 842 595] /Contents ${contentObjIds[i]} 0 R /Resources << /Font << /F1 ${FONT} 0 R /F2 ${FONTB} 0 R >> >> >>`
    );
  }

  writeObj(PAGES, `<< /Type /Pages /Kids [${pageObjIds.map((p) => `${p} 0 R`).join(' ')}] /Count ${pageObjIds.length} >>`);
  writeObj(CATALOG, `<< /Type /Catalog /Pages ${PAGES} 0 R >>`);

  const body = out.join('');
  let xrefPos = body.length;
  let xref = `xref\n0 ${id}\n0000000000 65535 f \n`;
  for (let i = 1; i < id; i += 1) {
    xref += `${String(offsets[i]).padStart(10, '0')} 00000 n \n`;
  }
  const trailer = `trailer\n<< /Size ${id} /Root ${CATALOG} 0 R >>\nstartxref\n${xrefPos}\n%%EOF`;
  return `%PDF-1.4\n${body}${xref}${trailer}`;
}

function pageStreamFromLines(drawLines) {
  // drawLines: array of { text, x, y, bold?, size? }
  const parts = ['BT'];
  let lastFont = null;
  let lastSize = null;
  for (const d of drawLines) {
    const size = d.size || 9;
    const font = d.bold ? 'F2' : 'F1';
    if (font !== lastFont || size !== lastSize) {
      parts.push(`/${font} ${size} Tf`);
      lastFont = font;
      lastSize = size;
    }
    parts.push(`1 0 0 1 ${d.x.toFixed(2)} ${d.y.toFixed(2)} Tm (${pdfEscape(d.text)}) Tj`);
  }
  parts.push('ET');
  return parts.join('\n');
}

function layoutSectionPages(sec, meta, generated) {
  const pageW = 842;
  const pageH = 595;
  const margin = 28;
  const usable = pageW - margin * 2;
  const cols = [
    { key: 'status', title: 'Status', width: 70 },
    { key: 'when', title: 'Day / Time', width: 90 },
    { key: 'module', title: 'Module', width: 210 },
    { key: 'facility', title: 'Facility', width: 120 },
    { key: 'lecturers', title: 'Lecturers', width: 170 },
    { key: 'groups', title: 'Groups', width: 90 },
  ];
  // normalize widths to usable
  const sumW = cols.reduce((s, c) => s + c.width, 0);
  cols.forEach((c) => {
    c.width = (c.width / sumW) * usable;
  });

  const stats = sectionConflictStats(sec);
  const rows = (sec.rows || []).filter((r) => r.status !== 'skipped' && !r.mergedAway);
  const program = sec.program?.name || 'Program not matched';
  const ay = meta.yearLabel || (meta.academicYearId ? `AY #${meta.academicYearId}` : '');
  const sem = meta.semester != null && meta.semester !== '' ? `Semester ${meta.semester}` : '';

  const tableRows = rows.map((r) => {
    const status = rowStatus(r);
    const detail = conflictDetails(r);
    const mod = detail ? `${moduleLabel(r)} | ${detail}` : moduleLabel(r);
    return {
      status,
      when: `${r.day || '—'} ${fmtTime(r.start)}-${fmtTime(r.end)}`,
      module: mod,
      facility: facilityLabel(r),
      lecturers: lecturersLabel(r),
      groups: groupsLabel(r),
      conflict: /^CONFLICT/i.test(status),
    };
  });

  const charW = (size) => size * 0.5; // approx for Helvetica
  const wrapCell = (text, width, size = 8) => wrapWords(text, Math.max(8, Math.floor(width / charW(size))));

  const pages = [];
  let draw = [];
  let y = pageH - margin;

  const flushPage = () => {
    pages.push({ stream: pageStreamFromLines(draw) });
    draw = [];
    y = pageH - margin;
  };

  const ensureSpace = (need) => {
    if (y - need < margin) flushPage();
  };

  const writeLine = (text, opts = {}) => {
    const size = opts.size || 9;
    const lines = wrapWords(text, Math.floor(usable / charW(size)));
    for (const ln of lines) {
      ensureSpace(size + 4);
      draw.push({ text: ln, x: margin, y: y - size, bold: opts.bold, size });
      y -= size + 3;
    }
  };

  writeLine(sec.title || 'Untitled section', { bold: true, size: 12 });
  writeLine(`Upload preview before save · Generated ${generated}${[ay, sem].filter(Boolean).length ? ` · ${[ay, sem].filter(Boolean).join(' · ')}` : ''}`, {
    size: 8,
  });
  writeLine(
    `Year ${sec.year || '—'} · Groups ${(sec.groupNumbers || []).join('&') || '—'} · ${program} · ${rows.length} row(s)`,
    { size: 8 }
  );
  if (stats.total) {
    writeLine(
      `${stats.total} conflict warning(s) — ${[
        stats.room ? `${stats.room} ROOM` : null,
        stats.group ? `${stats.group} GROUP` : null,
        stats.both ? `${stats.both} ROOM+GROUP` : null,
      ]
        .filter(Boolean)
        .join(' · ')} (review before save)`,
      { bold: true, size: 8 }
    );
  } else {
    writeLine('No ROOM/GROUP conflicts flagged on this preview', { size: 8 });
  }
  y -= 4;

  const drawHeader = () => {
    ensureSpace(16);
    let x = margin;
    for (const c of cols) {
      draw.push({ text: c.title, x, y: y - 9, bold: true, size: 8 });
      x += c.width;
    }
    y -= 14;
  };

  drawHeader();

  if (!tableRows.length) {
    writeLine('No rows', { size: 8 });
  }

  for (const tr of tableRows) {
    const cellLines = cols.map((c) => wrapCell(tr[c.key], c.width - 4, 7));
    const rowH = Math.max(...cellLines.map((l) => l.length)) * 9 + 4;
    if (y - rowH < margin) {
      flushPage();
      drawHeader();
    }
    let x = margin;
    for (let i = 0; i < cols.length; i += 1) {
      let yy = y - 8;
      for (const ln of cellLines[i]) {
        draw.push({ text: ln, x, y: yy, bold: tr.conflict && (i === 0 || i === 2), size: 7 });
        yy -= 9;
      }
      x += cols[i].width;
    }
    y -= rowH;
  }

  if (draw.length) flushPage();
  return pages;
}

/**
 * Build and download a PDF of upload-preview table(s) — no popup, no npm PDF libs.
 */
export async function exportUploadSectionsPdf(sections, meta = {}) {
  const list = (sections || []).filter(Boolean);
  if (!list.length) {
    throw new Error('No section to export');
  }

  const generated = new Date().toLocaleString();
  const allPages = [];
  for (const sec of list) {
    allPages.push(...layoutSectionPages(sec, meta, generated));
  }
  if (!allPages.length) {
    allPages.push({ stream: pageStreamFromLines([{ text: 'No content', x: 40, y: 550, size: 12 }]) });
  }

  // Use TextEncoder length correctly in browser
  const pdfSource = (() => {
    // rebuild with browser-safe byte length
    const pages = allPages;
    const out = [];
    const offsets = [0];
    let id = 1;

    const push = (s) => {
      out.push(s);
    };
    const writeObj = (oid, body) => {
      offsets[oid] = out.reduce((n, s) => n + s.length, 0);
      push(`${oid} 0 obj\n${body}\nendobj\n`);
    };
    const byteLen = (s) => new TextEncoder().encode(s).length;

    const FONT = id++;
    writeObj(FONT, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    const FONTB = id++;
    writeObj(FONTB, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');

    const contentObjIds = [];
    for (const page of pages) {
      const cid = id++;
      contentObjIds.push(cid);
      const stream = page.stream;
      writeObj(cid, `<< /Length ${byteLen(stream)} >>\nstream\n${stream}\nendstream`);
    }

    const pageObjIds = [];
    for (let i = 0; i < pages.length; i += 1) pageObjIds.push(id++);
    const PAGES = id++;
    const CATALOG = id++;

    for (let i = 0; i < pageObjIds.length; i += 1) {
      writeObj(
        pageObjIds[i],
        `<< /Type /Page /Parent ${PAGES} 0 R /MediaBox [0 0 842 595] /Contents ${contentObjIds[i]} 0 R /Resources << /Font << /F1 ${FONT} 0 R /F2 ${FONTB} 0 R >> >> >>`
      );
    }
    writeObj(PAGES, `<< /Type /Pages /Kids [${pageObjIds.map((p) => `${p} 0 R`).join(' ')}] /Count ${pageObjIds.length} >>`);
    writeObj(CATALOG, `<< /Type /Catalog /Pages ${PAGES} 0 R >>`);

    const body = out.join('');
    const xrefPos = byteLen(body);
    let xref = `xref\n0 ${id}\n0000000000 65535 f \n`;
    for (let i = 1; i < id; i += 1) {
      xref += `${String(offsets[i]).padStart(10, '0')} 00000 n \n`;
    }
    return `%PDF-1.4\n${body}${xref}trailer\n<< /Size ${id} /Root ${CATALOG} 0 R >>\nstartxref\n${xrefPos}\n%%EOF`;
  })();

  const fileName = safeFileName(
    meta.fileName || (list.length === 1 ? list[0].title : 'timetable-preview-all-sections')
  );
  const blob = new Blob([pdfSource], { type: 'application/pdf' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = fileName;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 2000);
  return fileName;
}
