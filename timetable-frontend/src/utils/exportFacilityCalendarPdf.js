/**
 * Zero-dependency PDF of facility week schedules (one file, all facilities).
 */
import { fmtTime } from './timeFormat.js';
import { capitalizePersonName, capitalizeCampusName } from './formatDisplay.js';

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function safeFileName(name) {
  const base = String(name || 'facility-schedules')
    .replace(/[^\w\-]+/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '')
    .slice(0, 80);
  return `${base || 'facility-schedules'}.pdf`;
}

function pdfEscape(s) {
  return String(s ?? '')
    .replace(/\\/g, '\\\\')
    .replace(/\(/g, '\\(')
    .replace(/\)/g, '\\)')
    .replace(/\r?\n/g, ' ')
    .replace(/[^\x20-\x7E]/g, (ch) => {
      try {
        return ch.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      } catch {
        return '?';
      }
    });
}

function wrapWords(text, maxChars) {
  const words = String(text || '')
    .split(/\s+/)
    .filter(Boolean);
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

function pageStreamFromLines(drawLines) {
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

function buildPdfBytes(pages) {
  const out = [];
  const offsets = [0];
  let id = 1;
  const push = (s) => out.push(s);
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
  writeObj(
    PAGES,
    `<< /Type /Pages /Kids [${pageObjIds.map((p) => `${p} 0 R`).join(' ')}] /Count ${pageObjIds.length} >>`
  );
  writeObj(CATALOG, `<< /Type /Catalog /Pages ${PAGES} 0 R >>`);

  const body = out.join('');
  const xrefPos = byteLen(body);
  let xref = `xref\n0 ${id}\n0000000000 65535 f \n`;
  for (let i = 1; i < id; i += 1) {
    xref += `${String(offsets[i]).padStart(10, '0')} 00000 n \n`;
  }
  return `%PDF-1.4\n${body}${xref}trailer\n<< /Size ${id} /Root ${CATALOG} 0 R >>\nstartxref\n${xrefPos}\n%%EOF`;
}

function moduleLabel(s) {
  const code = s.moduleCode || '';
  const name = s.moduleName || '';
  if (code && name && name !== code) return `${code} — ${name}`;
  return name || code || '—';
}

function groupsLabel(s) {
  const names = (s.groups || []).map((g) => g.name).filter(Boolean);
  return names.length ? names.join(', ') : '—';
}

function lecturersLabel(s) {
  const parts = [];
  if (s.leader?.names) parts.push(`${capitalizePersonName(s.leader.names)} (ML)`);
  for (const u of s.otherLecturers || []) {
    if (u?.names) parts.push(capitalizePersonName(u.names));
  }
  return parts.length ? parts.join(', ') : '—';
}

function facilityTitle(f) {
  const bits = [
    f?.name || 'Facility',
    capitalizeCampusName(f?.campus?.name) || null,
    f?.capacity != null ? `Cap ${f.capacity}` : null,
    f?.type || null,
    f?.buildName || null,
  ].filter(Boolean);
  return bits.join(' · ');
}

function sessionsForBlock(block) {
  const list = [...(block.sessions || [])];
  list.sort((a, b) => {
    const di = DAYS.indexOf(a.day) - DAYS.indexOf(b.day);
    if (di !== 0) return di;
    return String(a.startTime || '').localeCompare(String(b.startTime || ''));
  });
  return list;
}

function layoutFacilityPages(blocks, meta, generated) {
  const pageW = 842;
  const pageH = 595;
  const margin = 28;
  const usable = pageW - margin * 2;
  const cols = [
    { key: 'day', title: 'Day', width: 72 },
    { key: 'time', title: 'Time', width: 88 },
    { key: 'module', title: 'Module', width: 240 },
    { key: 'groups', title: 'Groups', width: 150 },
    { key: 'lecturers', title: 'Lecturers', width: 180 },
  ];
  const sumW = cols.reduce((s, c) => s + c.width, 0);
  cols.forEach((c) => {
    c.width = (c.width / sumW) * usable;
  });

  const ay =
    meta.yearLabel ||
    (meta.academicYearId ? `AY #${meta.academicYearId}` : '') ||
    '';
  const sem = meta.semester != null && meta.semester !== '' ? `Semester ${meta.semester}` : '';
  const campus = meta.campusLabel ? `Campus: ${meta.campusLabel}` : '';

  const charW = (size) => size * 0.5;
  const wrapCell = (text, width, size = 8) =>
    wrapWords(text, Math.max(8, Math.floor(width / charW(size))));

  const pages = [];
  let draw = [];
  let y = pageH - margin;
  let headerDrawn = false;

  const flushPage = () => {
    pages.push({ stream: pageStreamFromLines(draw) });
    draw = [];
    y = pageH - margin;
    headerDrawn = false;
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

  const drawDocHeader = () => {
    if (headerDrawn) return;
    writeLine('University of Rwanda — Facility schedules', { bold: true, size: 13 });
    writeLine(
      `Generated ${generated}${[ay, sem, campus].filter(Boolean).length ? ` · ${[ay, sem, campus].filter(Boolean).join(' · ')}` : ''} · ${blocks.length} facility(ies)`,
      { size: 8 }
    );
    y -= 6;
    headerDrawn = true;
  };

  const drawTableHeader = () => {
    ensureSpace(16);
    let x = margin;
    for (const c of cols) {
      draw.push({ text: c.title, x, y: y - 9, bold: true, size: 8 });
      x += c.width;
    }
    y -= 14;
  };

  drawDocHeader();

  for (const block of blocks) {
    const f = block.facility || {};
    const sessions = sessionsForBlock(block);
    ensureSpace(48);
    if (y < pageH - margin - 40) y -= 6;
    writeLine(facilityTitle(f), { bold: true, size: 11 });
    writeLine(
      `${sessions.length} session(s)${sessions.length ? '' : ' — entirely free'}`,
      { size: 8 }
    );
    y -= 2;

    if (!sessions.length) {
      writeLine('No sessions scheduled.', { size: 8 });
      y -= 4;
      continue;
    }

    drawTableHeader();

    for (const s of sessions) {
      const tr = {
        day: s.day || '—',
        time: `${fmtTime(s.startTime)}-${fmtTime(s.endTime)}`,
        module: moduleLabel(s),
        groups: groupsLabel(s),
        lecturers: lecturersLabel(s),
      };
      const cellLines = cols.map((c) => wrapCell(tr[c.key], c.width - 4, 7));
      const rowH = Math.max(...cellLines.map((l) => l.length)) * 9 + 4;
      if (y - rowH < margin) {
        flushPage();
        drawDocHeader();
        writeLine(facilityTitle(f) + ' (continued)', { bold: true, size: 10 });
        drawTableHeader();
      }
      let x = margin;
      for (let i = 0; i < cols.length; i += 1) {
        let yy = y - 8;
        for (const ln of cellLines[i]) {
          draw.push({ text: ln, x, y: yy, bold: false, size: 7 });
          yy -= 9;
        }
        x += cols[i].width;
      }
      y -= rowH;
    }
    y -= 8;
  }

  if (draw.length) flushPage();
  return pages;
}

/**
 * Download one PDF covering every facility block passed in.
 */
export async function exportFacilityCalendarPdf(blocks, meta = {}) {
  const list = (blocks || []).filter((b) => b?.facility);
  if (!list.length) {
    throw new Error('No facilities to export');
  }

  const generated = new Date().toLocaleString();
  const sorted = [...list].sort((a, b) =>
    String(a.facility?.name || '').localeCompare(String(b.facility?.name || ''))
  );
  const allPages = layoutFacilityPages(sorted, meta, generated);
  if (!allPages.length) {
    allPages.push({
      stream: pageStreamFromLines([{ text: 'No content', x: 40, y: 550, size: 12 }]),
    });
  }

  const pdfSource = buildPdfBytes(allPages);
  const fileName = safeFileName(meta.fileName || 'facility-schedules');
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
