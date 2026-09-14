const rawBase = import.meta.env.BASE_URL || '/';
const deployPath = rawBase.replace(/^\/+|\/+$/g, '');
const storagePrefix = deployPath.replace(/\//g, '_') || 'timetable';

export const TOKEN_STORAGE_KEY = `${storagePrefix}_token`;
export const USER_STORAGE_KEY = `${storagePrefix}_user`;

export const LOGIN_PATH = '/login';
export const APP_BASE = '/app';

export function appPath(sub = '') {
  const clean = String(sub || '').replace(/^\/+/, '');
  if (!clean) return APP_BASE;
  return `${APP_BASE}/${clean}`;
}

export function loginPath() {
  return LOGIN_PATH;
}

export function publicHref(path = '/') {
  const base = routerBasename();
  const clean = path === '/' ? '/' : `/${String(path).replace(/^\/+/, '')}`;
  if (!base || base === '/') return clean;
  if (clean === '/') return `${base}/`;
  return `${base}${clean}`;
}

export function routerBasename() {
  const base = import.meta.env.BASE_URL || '/';
  const trimmed = base.replace(/\/$/, '');
  return trimmed === '' ? '/' : trimmed;
}

export function publicAssetUrl(path = '') {
  const base = import.meta.env.BASE_URL || '/';
  const normalizedBase = base.endsWith('/') ? base : `${base}/`;
  const normalizedPath = String(path).replace(/^\/+/, '');
  return `${normalizedBase}${normalizedPath}`;
}
