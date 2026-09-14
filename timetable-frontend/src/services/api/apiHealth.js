/**
 * Tiny pub/sub so axios can signal "API unreachable" without React imports.
 */
const listeners = new Set();

let down = false;
let lastReason = '';
let failStreak = 0;

export function isApiDown() {
  return down;
}

export function getApiDownReason() {
  return lastReason;
}

export function subscribeApiHealth(fn) {
  listeners.add(fn);
  fn({ down, reason: lastReason });
  return () => listeners.delete(fn);
}

function emit() {
  const payload = { down, reason: lastReason };
  listeners.forEach((fn) => {
    try {
      fn(payload);
    } catch {
      /* ignore subscriber errors */
    }
  });
}

export function reportApiUp() {
  failStreak = 0;
  if (!down) return;
  down = false;
  lastReason = '';
  emit();
}

export function reportApiDown(reason = 'unreachable') {
  failStreak += 1;
  // One clear network/gateway failure is enough — pages should not spam error UIs
  if (!down) {
    down = true;
    lastReason = reason;
    emit();
  } else if (reason && reason !== lastReason) {
    lastReason = reason;
    emit();
  }
}

/** True when the browser could not reach the API (or gateway is dead). */
export function isApiUnreachableError(error) {
  if (!error) return false;
  if (error.config?.__apiHealthProbe) return false;
  if (error.config?.__skipApiHealth) return false;

  const status = error.response?.status;
  if (status === 502 || status === 503 || status === 504) return true;

  // No HTTP response → connection refused / DNS / offline / CORS blocked as network
  if (!error.response) {
    const code = String(error.code || '');
    const msg = String(error.message || '').toLowerCase();
    if (
      code === 'ERR_NETWORK' ||
      msg.includes('network error') ||
      msg.includes('failed to fetch')
    ) {
      return true;
    }
    // Timeouts on long exports should not trigger the global offline overlay
    if (code === 'ECONNABORTED' || msg.includes('timeout')) {
      return false;
    }
    if (!status) return true;
  }
  return false;
}
