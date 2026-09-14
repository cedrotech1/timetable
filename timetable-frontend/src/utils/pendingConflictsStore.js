import { TOKEN_STORAGE_KEY } from '../utils/appPaths.js';

const KEY = 'ur_timetable_pending_conflicts';

function read() {
  try {
    const raw = localStorage.getItem(KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function write(items) {
  localStorage.setItem(KEY, JSON.stringify(items.slice(0, 200)));
}

/**
 * Persist failed / conflicted set-timetable rows so staff can revisit and fix.
 */
export const pendingConflictsStore = {
  list() {
    return read().sort((a, b) => String(b.createdAt).localeCompare(String(a.createdAt)));
  },

  addMany(entries) {
    if (!entries?.length) return read();
    const now = new Date().toISOString();
    const next = [
      ...entries.map((e) => ({
        id: e.id || `pc-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        createdAt: now,
        source: e.source || 'set-timetable',
        mode: e.mode || 'unknown',
        message: e.message || 'Conflict / not saved',
        attempt: e.attempt || null,
        conflicts: e.conflicts || null,
        payload: e.payload || null, // raw row for retry context
        resolved: false,
      })),
      ...read(),
    ];
    write(next);
    return next;
  },

  markResolved(id) {
    const next = read().map((e) => (e.id === id ? { ...e, resolved: true } : e));
    write(next);
    return next;
  },

  remove(id) {
    const next = read().filter((e) => e.id !== id);
    write(next);
    return next;
  },

  clearResolved() {
    const next = read().filter((e) => !e.resolved);
    write(next);
    return next;
  },

  clearAll() {
    write([]);
    return [];
  },
};

export function pendingConflictsCount() {
  return read().filter((e) => !e.resolved).length;
}

// Keep key namespaced per deploy if needed later
export function pendingConflictsStorageKey() {
  return `${TOKEN_STORAGE_KEY}_pending_conflicts`.replace('_token', '');
}
